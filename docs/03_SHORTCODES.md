# Shortcodes Reference

Complete reference for all available shortcodes in the WordPress Agency Starter Kit.

---

## 📋 Table of Contents

- [Quick Reference](#quick-reference)
- [Layout Shortcodes](#layout-shortcodes)
- [Content Shortcodes](#content-shortcodes)
- [Interactive Shortcodes](#interactive-shortcodes)
- [Filter Shortcodes](#filter-shortcodes)
- [Stats & Numbers](#stats--numbers)
- [Media Shortcodes](#media-shortcodes)
- [Utility Shortcodes](#utility-shortcodes)
- [WooCommerce Shortcodes](#woocommerce-shortcodes)

---

## 🚀 Quick Reference

| Shortcode | Category | Purpose |
|-----------|----------|---------|
| `[accordion]` | Layout | Collapsible content sections |
| `[tabs]` | Layout | Tabbed content |
| `[timeline]` | Layout | Vertical timeline |
| `[modal]` | Layout | Popup modal dialog |
| `[hero_slider]` | Content | Manual hero slider |
| `[hero_slider_query]` | Content | Hero slider from CPT |
| `[carousel]` | Content | Image/content carousel |
| `[testimonials]` | Content | Client testimonials |
| `[team_cards]` | Content | Team member cards |
| `[posts_grid]` | Content | Posts in grid layout |
| `[ajax_search]` | Interactive | Live search |
| `[ajax_filters]` | Interactive | Advanced filtering |
| `[posts_load_more]` | Interactive | Infinite scroll |
| `[stats]` | Stats | Animated statistics |
| `[pricing_table]` | Stats | Pricing comparison |
| `[video_player]` | Media | Custom video player |
| `[notification]` | Utility | Alert messages |
| `[google_map]` | Utility | GDPR maps |

---

## 📐 Layout Shortcodes

### Accordion

Collapsible content sections.

**Syntax:**
```
[accordion]
  [accordion_item title="Section 1" open="true"]
    Content for section 1
  [/accordion_item]
  [accordion_item title="Section 2"]
    Content for section 2
  [/accordion_item]
[/accordion]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `style` | string | `default` | Style variant: `default`, `bordered`, `minimal` |
| `icon` | string | `chevron` | Icon type: `chevron`, `plus`, `arrow` |

**Item Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `title` | string | required | Section title |
| `open` | bool | `false` | Open by default |

**Example:**
```
[accordion style="bordered" icon="plus"]
  [accordion_item title="What is WordPress?" open="true"]
    WordPress is a free and open-source content management system.
  [/accordion_item]
  [accordion_item title="What is ACF?"]
    Advanced Custom Fields is a WordPress plugin for creating custom fields.
  [/accordion_item]
[/accordion]
```

---

### Tabs

Tabbed content sections.

**Syntax:**
```
[tabs]
  [tab title="Tab 1" active="true"]
    Content for tab 1
  [/tab]
  [tab title="Tab 2"]
    Content for tab 2
  [/tab]
[/tabs]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `style` | string | `horizontal` | Layout: `horizontal`, `vertical` |
| `position` | string | `top` | Tab position: `top`, `bottom`, `left`, `right` |

**Tab Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `title` | string | required | Tab label |
| `icon` | string | - | Icon class (optional) |
| `active` | bool | `false` | Active by default |

**Example:**
```
[tabs style="horizontal"]
  [tab title="Features" active="true"]
    <ul>
      <li>Easy to use</li>
      <li>Fully responsive</li>
      <li>SEO optimized</li>
    </ul>
  [/tab]
  [tab title="Specifications"]
    <p>Technical specifications here.</p>
  [/tab]
[/tabs]
```

---

### Timeline

Vertical timeline for events/history.

**Syntax:**
```
[timeline]
  [timeline_item date="2024" title="Event 1"]
    Description of event 1
  [/timeline_item]
  [timeline_item date="2023" title="Event 2"]
    Description of event 2
  [/timeline_item]
[/timeline]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `style` | string | `default` | Style: `default`, `minimal`, `modern` |
| `align` | string | `left` | Alignment: `left`, `center`, `alternate` |

**Item Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `date` | string | required | Date/year |
| `title` | string | required | Event title |
| `icon` | string | - | Icon class |

**Example:**
```
[timeline style="modern" align="alternate"]
  [timeline_item date="2024" title="Company Founded"]
    We started our journey to revolutionize the industry.
  [/timeline_item]
  [timeline_item date="2025" title="First Product Launch"]
    Successfully launched our flagship product.
  [/timeline_item]
[/timeline]
```

---

### Modal

Popup modal dialog.

**Syntax:**
```
[modal button_text="Open Modal" title="Modal Title"]
  Modal content goes here
[/modal]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `button_text` | string | `Open` | Button label |
| `button_style` | string | `primary` | Button style |
| `title` | string | - | Modal title |
| `size` | string | `medium` | Size: `small`, `medium`, `large`, `full` |
| `animation` | string | `fade` | Animation: `fade`, `slide`, `zoom` |

**Example:**
```
[modal button_text="View Details" title="Product Details" size="large"]
  <h3>Product Features</h3>
  <p>Detailed product information...</p>
[/modal]
```

---

## 📄 Content Shortcodes

### Hero Slider (Manual)

Manual hero slider with nested slides.

**Syntax:**
```
[hero_slider autoplay="true" delay="5000"]
  [hero_slide image="url" title="Slide 1" subtitle="Subtitle" button_text="Learn More" button_url="#"]
    Slide description
  [/hero_slide]
  [hero_slide image="url" title="Slide 2"]
    Another slide
  [/hero_slide]
[/hero_slider]
```

**Container Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `autoplay` | bool | `true` | Auto-advance slides |
| `delay` | int | `5000` | Delay in ms |
| `loop` | bool | `true` | Loop slides |
| `navigation` | bool | `true` | Show arrows |
| `pagination` | bool | `true` | Show dots |

**Slide Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `image` | string | required | Image URL |
| `title` | string | required | Slide title |
| `subtitle` | string | - | Subtitle |
| `button_text` | string | - | CTA button text |
| `button_url` | string | - | CTA button URL |
| `overlay_opacity` | int | `30` | Overlay opacity (0-100) |

---

### Hero Slider (Query)

Hero slider loading from Custom Post Type.

**Syntax:**
```
[hero_slider_query limit="5" autoplay="true" delay="5000"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | `3` | Number of slides |
| `autoplay` | bool | `true` | Auto-advance |
| `delay` | int | `5000` | Delay in ms |
| `loop` | bool | `true` | Loop slides |
| `order` | string | `ASC` | Sort order |
| `orderby` | string | `menu_order` | Sort field |

**Backend Setup:**

1. Go to **Hero Slides → Add New**
2. Enter Title
3. Enter Subtitle
4. Upload Desktop Image (1920x1080px recommended)
5. Upload Mobile Image (800x600px recommended, optional)
6. Enter Button Text
7. Enter Button URL
8. Select Button Style (primary/secondary/outline)
9. Set Overlay Opacity (0-100%)
10. Choose Text Color (light/dark)
11. Publish

**Example:**
```
[hero_slider_query limit="3" autoplay="true" delay="7000" loop="true"]
```

---

### Carousel

Image or content carousel.

**Syntax:**
```
[carousel category="featured" limit="6" autoplay="true"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `category` | string | - | Carousel category |
| `limit` | int | `6` | Number of items |
| `columns` | int | `3` | Items per view |
| `autoplay` | bool | `true` | Auto-advance |
| `delay` | int | `3000` | Delay in ms |
| `loop` | bool | `true` | Loop items |

**Example:**
```
[carousel category="clients" limit="8" columns="4" autoplay="true"]
```

---

### Testimonials

Display client testimonials.

**Syntax:**
```
[testimonials category="featured" limit="6" layout="grid" columns="3"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `category` | string | - | Testimonial category |
| `limit` | int | `6` | Number of items |
| `layout` | string | `grid` | Layout: `grid`, `carousel`, `list` |
| `columns` | int | `3` | Grid columns |
| `show_rating` | bool | `true` | Show star rating |
| `show_image` | bool | `true` | Show author image |

**Example:**
```
[testimonials category="featured" limit="6" layout="carousel" show_rating="true"]
```

---

### Team Cards

Display team members.

**Syntax:**
```
[team_cards department="management" limit="8" columns="4"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `department` | string | - | Filter by department |
| `position` | string | - | Filter by position |
| `limit` | int | `12` | Number of members |
| `columns` | int | `4` | Grid columns |
| `show_bio` | bool | `true` | Show biography |
| `show_social` | bool | `true` | Show social links |

**Example:**
```
[team_cards department="development" limit="8" columns="4" show_social="true"]
```

---

### Posts Grid

Display posts in grid layout.

**Syntax:**
```
[posts_grid post_type="post" category="news" limit="9" columns="3"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `post_type` | string | `post` | Post type |
| `category` | string | - | Category slug |
| `limit` | int | `9` | Number of posts |
| `columns` | int | `3` | Grid columns |
| `show_excerpt` | bool | `true` | Show excerpt |
| `show_date` | bool | `true` | Show date |
| `show_author` | bool | `false` | Show author |
| `excerpt_length` | int | `20` | Excerpt words |

**Example:**
```
[posts_grid post_type="project" category="web-design" limit="6" columns="3" show_excerpt="true"]
```

---

## 🎮 Interactive Shortcodes

### AJAX Search

Live search with dropdown results.

**Syntax:**
```
[ajax_search placeholder="Search..." show_submit="true"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `placeholder` | string | `Search...` | Input placeholder |
| `show_submit` | bool | `true` | Show submit button |
| `min_chars` | int | `2` | Min characters to search |
| `max_results` | int | `5` | Max dropdown results |
| `post_types` | string | `post,page` | Comma-separated post types |
| `include_products` | bool | `true` | Include WooCommerce products |

**Features:**
- Live search with debounce (300ms)
- Dropdown with results
- Submit button to full results page
- Post type color coding
- Thumbnail images
- "No results" message

**Example:**
```
[ajax_search placeholder="Search our site..." show_submit="true" min_chars="3" max_results="8"]
```

---

### AJAX Filters

Advanced filtering system with multiple filter types.

**Syntax:**
```
[ajax_filters post_type="job" posts_per_page="12" template="card" columns="3"]
  [filter_search placeholder="Search..." label="Search"]
  [filter_taxonomy taxonomy="category" type="checkbox" label="Category"]
  [filter_range key="price" min="0" max="1000" step="10" label="Price"]
[/ajax_filters]
```

**Container Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `post_type` | string | `post` | Post type to filter |
| `posts_per_page` | int | `12` | Results per page |
| `template` | string | `card` | Template: `card`, `list`, `grid`, `job`, `product` |
| `columns` | int | `3` | Grid columns (1-4) |
| `show_count` | bool | `true` | Show result count |
| `show_sort` | bool | `true` | Show sort dropdown |
| `show_reset` | bool | `true` | Show reset button |

**Features:**
- Multiple filter types
- Real-time AJAX filtering
- Pagination
- Active filter tags
- URL parameters (shareable links)
- Loading states
- Sort options
- Reset functionality

See [Filter Shortcodes](#filter-shortcodes) for individual filter types.

**Example: Job Board**
```
[ajax_filters post_type="job" posts_per_page="12" template="job" columns="2"]
  [filter_search placeholder="Search jobs..." label="Search"]
  [filter_taxonomy taxonomy="job_category" type="checkbox" label="Category" show_count="true"]
  [filter_taxonomy taxonomy="job_type" type="buttons" label="Type"]
  [filter_taxonomy taxonomy="job_location" type="dropdown" label="Location"]
  [filter_range key="salary_min" min="0" max="150000" step="5000" label="Minimum Salary" suffix=" €"]
[/ajax_filters]
```

**Example: E-Commerce**
```
[ajax_filters post_type="product" posts_per_page="16" template="product" columns="4"]
  [filter_search placeholder="Search products..."]
  [filter_taxonomy taxonomy="product_cat" type="checkbox" label="Categories"]
  [filter_range key="_price" min="0" max="1000" step="10" label="Price" suffix=" €"]
  [filter_taxonomy taxonomy="pa_color" type="buttons" label="Color"]
[/ajax_filters]
```

---

### Posts Load More

Infinite scroll or "Load More" button.

**Syntax:**
```
[posts_load_more post_type="post" posts_per_page="9" button_text="Load More"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `post_type` | string | `post` | Post type |
| `posts_per_page` | int | `9` | Posts per load |
| `button_text` | string | `Load More` | Button label |
| `button_style` | string | `primary` | Button style |
| `loading_text` | string | `Loading...` | Loading text |
| `no_more_text` | string | `No more posts` | End text |
| `auto_load` | bool | `false` | Infinite scroll |

**Example:**
```
[posts_load_more post_type="project" posts_per_page="6" button_text="Show More Projects" auto_load="false"]
```

---

## 🔍 Filter Shortcodes

### Filter Search

Text search filter (used inside `ajax_filters`).

**Syntax:**
```
[filter_search placeholder="Search..." label="Search"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `placeholder` | string | `Search...` | Input placeholder |
| `label` | string | `Search` | Filter label |

---

### Filter Taxonomy

Taxonomy filter (used inside `ajax_filters`).

**Syntax:**
```
[filter_taxonomy taxonomy="category" type="checkbox" label="Category" show_count="true"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `taxonomy` | string | required | Taxonomy slug |
| `type` | string | `checkbox` | Type: `checkbox`, `radio`, `dropdown`, `buttons` |
| `label` | string | required | Filter label |
| `show_count` | bool | `true` | Show term counts |
| `operator` | string | `IN` | Operator: `IN`, `AND`, `NOT IN` |

**Types:**
- **checkbox** - Multiple selection with checkboxes
- **radio** - Single selection with radio buttons
- **dropdown** - Single selection dropdown
- **buttons** - Toggle buttons (multiple selection)

**Example - Checkboxes:**
```
[filter_taxonomy taxonomy="job_category" type="checkbox" label="Job Category" show_count="true"]
```

**Example - Buttons:**
```
[filter_taxonomy taxonomy="product_cat" type="buttons" label="Categories" show_count="false"]
```

**Example - Dropdown:**
```
[filter_taxonomy taxonomy="job_location" type="dropdown" label="Location" show_count="true"]
```

---

### Filter Range

Range slider filter for numeric meta fields (used inside `ajax_filters`).

**Syntax:**
```
[filter_range key="price" min="0" max="1000" step="10" label="Price" prefix="" suffix=" €"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `key` | string | required | Meta key to filter |
| `min` | int | `0` | Minimum value |
| `max` | int | `100` | Maximum value |
| `step` | int | `1` | Step increment |
| `label` | string | required | Filter label |
| `prefix` | string | - | Value prefix (e.g., "$") |
| `suffix` | string | - | Value suffix (e.g., " €", " km") |

**Common Use Cases:**

**Price Range:**
```
[filter_range key="_price" min="0" max="500" step="10" label="Price" suffix=" €"]
```

**Salary Range:**
```
[filter_range key="salary_min" min="20000" max="150000" step="5000" label="Minimum Salary" suffix=" € / year"]
```

**Size Range:**
```
[filter_range key="sqm" min="20" max="500" step="10" label="Size" suffix=" m²"]
```

**Distance Range:**
```
[filter_range key="distance" min="0" max="100" step="5" label="Distance" suffix=" km"]
```

---

## 📊 Stats & Numbers

### Stats

Animated statistics/counters.

**Syntax:**
```
[stats]
  [stat number="500" suffix="+" label="Happy Clients" icon="users"]
  [stat number="1000" suffix="+" label="Projects Completed" icon="check"]
  [stat number="50" suffix="" label="Team Members" icon="people"]
[/stats]
```

**Container Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `layout` | string | `grid` | Layout: `grid`, `row` |
| `columns` | int | `4` | Grid columns |

**Stat Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `number` | int | required | Counter number |
| `prefix` | string | - | Number prefix |
| `suffix` | string | - | Number suffix |
| `label` | string | required | Stat label |
| `icon` | string | - | Icon class |
| `color` | string | `primary` | Color scheme |

**Example:**
```
[stats columns="3"]
  [stat number="25" suffix="K+" label="Active Users" color="primary"]
  [stat number="500" suffix="+" label="5-Star Reviews" color="success"]
  [stat number="99" suffix="%" label="Satisfaction Rate" color="warning"]
[/stats]
```

---

### Pricing Table

Pricing comparison table.

**Syntax:**
```
[pricing_table]
  [pricing_plan title="Basic" price="9.99" period="month" featured="false"]
    <ul>
      <li>Feature 1</li>
      <li>Feature 2</li>
    </ul>
  [/pricing_plan]
  [pricing_plan title="Pro" price="19.99" period="month" featured="true"]
    <ul>
      <li>All Basic features</li>
      <li>Feature 3</li>
    </ul>
  [/pricing_plan]
[/pricing_table]
```

**Table Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `columns` | int | `3` | Number of columns |
| `style` | string | `default` | Style variant |

**Plan Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `title` | string | required | Plan name |
| `price` | string | required | Price amount |
| `currency` | string | `€` | Currency symbol |
| `period` | string | `month` | Billing period |
| `featured` | bool | `false` | Highlight plan |
| `button_text` | string | `Choose Plan` | CTA text |
| `button_url` | string | `#` | CTA URL |

---

## 🎬 Media Shortcodes

### Video Player

Custom video player with controls.

**Syntax:**
```
[video_player url="video.mp4" poster="poster.jpg" controls="true"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `url` | string | required | Video URL |
| `poster` | string | - | Poster image URL |
| `controls` | bool | `true` | Show controls |
| `autoplay` | bool | `false` | Auto-play video |
| `loop` | bool | `false` | Loop video |
| `muted` | bool | `false` | Mute audio |
| `width` | string | `100%` | Player width |
| `aspect_ratio` | string | `16:9` | Aspect ratio |

**Example:**
```
[video_player url="https://example.com/video.mp4" poster="poster.jpg" controls="true" autoplay="false"]
```

---

### Image Comparison

Before/After image slider.

**Syntax:**
```
[image_comparison before="before.jpg" after="after.jpg" label_before="Before" label_after="After"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `before` | string | required | Before image URL |
| `after` | string | required | After image URL |
| `label_before` | string | `Before` | Before label |
| `label_after` | string | `After` | After label |
| `position` | int | `50` | Initial position (0-100) |

---

### Logo Carousel

Animated logo carousel.

**Syntax:**
```
[logo_carousel category="clients" autoplay="true" speed="slow"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `category` | string | - | Logo category |
| `limit` | int | `12` | Number of logos |
| `autoplay` | bool | `true` | Auto-scroll |
| `speed` | string | `medium` | Speed: `slow`, `medium`, `fast` |
| `direction` | string | `left` | Direction: `left`, `right` |

---

## 🛠️ Utility Shortcodes

### Notification

Alert/notification boxes.

**Syntax:**
```
[notification type="info" dismissible="true" icon="info-circle"]
  Your notification message here
[/notification]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `type` | string | `info` | Type: `info`, `success`, `warning`, `error` |
| `dismissible` | bool | `false` | Show close button |
| `icon` | string | - | Icon class |
| `title` | string | - | Notification title |

**Example:**
```
[notification type="success" dismissible="true" title="Success!"]
  Your form has been submitted successfully.
[/notification]
```

---

### Spoiler

Collapsible spoiler content.

**Syntax:**
```
[spoiler title="Click to reveal"]
  Hidden content here
[/spoiler]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `title` | string | `Show more` | Spoiler button text |
| `open` | bool | `false` | Open by default |

---

### Google Map

GDPR-compliant Google Maps.

**Syntax:**
```
[google_map location_id="123" height="400px"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `location_id` | int | required | Google Map CPT ID |
| `height` | string | `400px` | Map height |
| `zoom` | int | `14` | Zoom level (1-20) |

**Backend Setup:**

1. Go to **Google Maps → Add New**
2. Enter location name
3. Enter address
4. Set coordinates (or auto-detect)
5. Enter privacy notice text
6. Customize marker icon (optional)
7. Publish
8. Note the Post ID

**Example:**
```
[google_map location_id="123" height="500px" zoom="15"]
```

---

## 🛒 WooCommerce Shortcodes

### Product Grid

Display WooCommerce products.

**Syntax:**
```
[product_grid category="featured" limit="8" columns="4"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `category` | string | - | Product category |
| `tag` | string | - | Product tag |
| `limit` | int | `8` | Number of products |
| `columns` | int | `4` | Grid columns |
| `orderby` | string | `date` | Order by |
| `order` | string | `DESC` | Sort order |
| `on_sale` | bool | `false` | Show only sale items |

---

### Add to Cart Button

Standalone add to cart button.

**Syntax:**
```
[add_to_cart id="123" style="primary" show_price="true"]
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | int | required | Product ID |
| `style` | string | `primary` | Button style |
| `show_price` | bool | `true` | Show price |
| `text` | string | - | Custom button text |

---

## 💡 Best Practices

### Performance

- **Limit results:** Use reasonable `limit` values
- **Lazy load images:** Enable lazy loading for carousels
- **Cache results:** Use WordPress caching plugins
- **Optimize images:** Compress images before upload

### Accessibility

- **Alt text:** Always provide alt text for images
- **Keyboard navigation:** All interactive elements keyboard accessible
- **Screen readers:** Use semantic HTML
- **ARIA labels:** Include ARIA labels where needed

### Responsive Design

- **Test mobile:** Always test on mobile devices
- **Columns:** Use responsive column counts
- **Touch targets:** Minimum 44x44px for touch elements
- **Font sizes:** Use responsive font sizes

### SEO

- **Headings:** Use proper heading hierarchy
- **Content:** Provide meaningful content
- **Links:** Use descriptive link text
- **Schema:** Add structured data where applicable

---

## 🔗 Related Documentation

- [AJAX Filters Guide](./AJAX-FILTERS.md) - Detailed filter system documentation
- [Custom Post Types](./CUSTOM-POST-TYPES.md) - CPT documentation
- [ACF Fields](./ACF-FIELDS.md) - Field reference
- [Development Guide](./DEVELOPMENT.md) - Creating custom shortcodes

---

## 📞 Need Help?

- Check [Troubleshooting](./TROUBLESHOOTING.md)
- Search [GitHub Issues](https://github.com/your-agency/wordpress-starter-kit/issues)
- Contact: support@your-agency.com

---

**Last Updated:** February 2026