# Usage Guide

Complete guide for content managers and editors using the WordPress Agency Starter Kit.

---

## 📋 Table of Contents

- [Getting Started](#getting-started)
- [Dashboard Overview](#dashboard-overview)
- [Creating Content](#creating-content)
- [Using Custom Post Types](#using-custom-post-types)
- [Working with Shortcodes](#working-with-shortcodes)
- [Building Pages](#building-pages)
- [Media Management](#media-management)
- [Menus and Navigation](#menus-and-navigation)
- [SEO Best Practices](#seo-best-practices)
- [Common Tasks](#common-tasks)
- [Tips & Tricks](#tips--tricks)

---

## 🚀 Getting Started

### Logging In

1. Navigate to your website: `https://your-site.com`
2. Add `/wp-admin` to the URL: `https://your-site.com/wp-admin`
3. Enter your username and password
4. Click "Log In"

**Forgot Password?**
1. Click "Lost your password?"
2. Enter your email address
3. Check your email for reset link
4. Follow instructions to reset

### First Time Setup

After logging in for the first time:

1. **Update Your Profile**
   - Go to **Users → Your Profile**
   - Update your name and email
   - Change your password (recommended)
   - Click "Update Profile"

2. **Familiarize Yourself with the Dashboard**
   - Explore the left sidebar menu
   - Check available post types
   - Review settings (if you have permission)

---

## 📊 Dashboard Overview

### Left Sidebar Menu

The main navigation menu includes:

| Menu Item | Purpose |
|-----------|---------|
| **Dashboard** | Overview and quick links |
| **Posts** | Standard blog posts |
| **Media** | Images, videos, files |
| **Pages** | Static pages |
| **Jobs** | Job postings |
| **Projects** | Portfolio items |
| **Team** | Team members |
| **Services** | Service offerings |
| **Testimonials** | Client reviews |
| **Hero Slides** | Homepage sliders |
| **Carousel** | Image carousels |
| **FAQ** | Frequently asked questions |
| **Google Maps** | GDPR-compliant maps |
| **Products** | WooCommerce products (if enabled) |
| **Comments** | Comment management |
| **Appearance** | Themes, menus, widgets |
| **Plugins** | Plugin management (admin only) |
| **Users** | User management (admin only) |
| **Settings** | Site settings (admin only) |

### Top Bar

- **Site Name** - Click to view your website
- **New** - Quick-create content
- **Comments** - Comment notifications
- **Your Profile** - Account settings and logout

---

## ✍️ Creating Content

### Creating a New Post

1. Go to **Posts → Add New**
2. Enter post title in the top field
3. Add content in the editor
4. Set categories (right sidebar)
5. Add tags (right sidebar)
6. Set featured image (right sidebar)
7. Click "Publish" when ready

### Content Editor

The WordPress editor (Gutenberg) uses blocks:

**Adding Blocks:**
1. Click the `+` button
2. Search for block type (Paragraph, Image, etc.)
3. Click to add

**Common Blocks:**
- **Paragraph** - Standard text
- **Heading** - H2, H3, etc.
- **Image** - Single image
- **Gallery** - Multiple images
- **List** - Bullet or numbered list
- **Quote** - Blockquote
- **Button** - Call-to-action button
- **Shortcode** - Custom shortcodes (see below)

**Block Settings:**
- Click any block to see settings in right sidebar
- Adjust alignment, colors, spacing
- Use toolbar for formatting (Bold, Italic, Link)

### Saving & Publishing

**Draft** - Save without publishing
- Click "Save Draft" button
- Content not visible to public

**Pending Review** - Submit for approval
- Click "Submit for Review"
- Editor/Admin must approve

**Publish** - Make content live
- Click "Publish" button
- Confirm by clicking "Publish" again
- Content immediately visible

**Schedule** - Publish at future date
- Click "Publish" dropdown
- Select date and time
- Click "Schedule"

---

## 📝 Using Custom Post Types

### Jobs

**Creating a Job Posting:**

1. Go to **Jobs → Add New**
2. Enter job title (e.g., "Senior Developer")
3. Add job description in editor
4. Fill in custom fields (right sidebar):
   - **Employment Type**: Full-time, Part-time, etc.
   - **Remote Work**: Yes/No
   - **Salary Min/Max**: Numeric values
   - **Experience Required**: Years
   - **Application Deadline**: Date picker
   - **Featured**: Yes/No (highlights job)
5. Set taxonomies:
   - **Job Category**: Development, Design, etc.
   - **Job Type**: Full-time, Contract, etc.
   - **Job Location**: City/Region
6. Set featured image (company logo or office)
7. Click "Publish"

**Tips:**
- Use clear, descriptive job titles
- Include salary range for better applications
- Mark urgent positions as "Featured"
- Use categories to organize by department

---

### Projects

**Creating a Portfolio Project:**

1. Go to **Projects → Add New**
2. Enter project name
3. Add project description
4. Fill in custom fields:
   - **Client Name**: Company/Person
   - **Project Date**: Completion date
   - **Project URL**: Website link (if applicable)
   - **Project Type**: Website, Branding, etc.
   - **Technologies Used**: Comma-separated list
5. Set **Project Category** (e.g., Web Design, Development)
6. Add featured image (project screenshot)
7. Add gallery images (if applicable)
8. Click "Publish"

**Best Practices:**
- Use high-quality screenshots
- Write clear project descriptions
- Include client testimonials (if available)
- Link to live projects when possible

---

### Team Members

**Adding a Team Member:**

1. Go to **Team → Add New**
2. Enter team member name
3. Add biography in editor
4. Fill in custom fields:
   - **Position**: Job title
   - **Email**: Contact email
   - **Phone**: Contact phone
   - **LinkedIn**: Profile URL
   - **Twitter**: Handle
   - **Skills**: Comma-separated
5. Set taxonomies:
   - **Department**: Sales, Development, etc.
   - **Position**: Manager, Developer, etc.
6. Upload profile photo as featured image
7. Click "Publish"

**Photo Guidelines:**
- Square format (1:1 ratio)
- Professional headshot
- Good lighting
- Neutral background
- Min 500x500px

---

### Services

**Adding a Service:**

1. Go to **Services → Add New**
2. Enter service name
3. Add service description
4. Fill in custom fields:
   - **Price From**: Starting price
   - **Duration**: Typical timeframe
   - **Icon**: Icon class (optional)
   - **Features**: List of features
5. Set **Service Category**
6. Add featured image (service illustration)
7. Click "Publish"

---

### Testimonials

**Adding a Client Testimonial:**

1. Go to **Testimonials → Add New**
2. Enter client name as title
3. Add testimonial quote in editor
4. Fill in custom fields:
   - **Client Position**: Job title
   - **Company Name**: Company
   - **Company URL**: Website
   - **Rating**: 1-5 stars
   - **Project Type**: What you did for them
5. Set **Industry** taxonomy
6. Upload client photo as featured image
7. Click "Publish"

**Writing Tips:**
- Keep quotes concise (2-4 sentences)
- Highlight specific results
- Get permission before publishing
- Use real photos when possible

---

### Hero Slides

**Creating a Homepage Slider:**

1. Go to **Hero Slides → Add New**
2. Enter slide title (main heading)
3. Add slide content in editor (optional description)
4. Fill in custom fields:
   - **Subtitle**: Smaller text above/below title
   - **Button Text**: CTA button text
   - **Button URL**: Where button links to
   - **Button Style**: Primary, Secondary, Outline
   - **Desktop Image**: Large image (1920x1080px)
   - **Mobile Image**: Mobile image (800x600px, optional)
   - **Overlay Opacity**: 0-100% (darkens image)
   - **Text Color**: Light or Dark
5. Click "Publish"

**Then use shortcode on homepage:**
```
[hero_slider_query limit="3"]
```

**Image Guidelines:**
- Desktop: 1920x1080px minimum
- Mobile: 800x600px (or same as desktop)
- JPG format for photos
- PNG for graphics with transparency
- Compress images before upload

---

### Carousel

**Creating an Image Carousel:**

1. Go to **Carousel → Add New**
2. Enter item title
3. Add caption in editor (optional)
4. Fill in custom fields:
   - **Link URL**: Where image links to (optional)
   - **Order**: Display order (lower numbers first)
5. Set **Carousel Category** (for filtering)
6. Upload image as featured image
7. Click "Publish"

**Create multiple carousel items, then use:**
```
[carousel category="featured" limit="6"]
```

---

### FAQ

**Adding FAQ Items:**

1. Go to **FAQ → Add New**
2. Enter question as title
3. Add answer in editor
4. Set **FAQ Category** (for grouping)
5. Set **Order** custom field (display order)
6. Click "Publish"

**Display FAQs:**
```
[faq_list category="general" limit="10"]
```

---

### Google Maps

**Adding a Map Location:**

1. Go to **Google Maps → Add New**
2. Enter location name
3. Fill in custom fields:
   - **Address**: Full address
   - **Latitude**: Coordinate (or auto-detect)
   - **Longitude**: Coordinate (or auto-detect)
   - **Zoom Level**: 1-20 (14 recommended)
   - **Privacy Notice**: GDPR text
   - **Marker Icon**: Custom icon URL (optional)
4. Click "Publish"
5. Note the Post ID (shown in URL)

**Use on page:**
```
[google_map location_id="123" height="400px"]
```

---

## 🎨 Working with Shortcodes

### What are Shortcodes?

Shortcodes are simple codes that add complex features to your pages:
```
[shortcode_name parameter="value"]
```

### Adding a Shortcode

1. Edit a page or post
2. Add a **Shortcode Block**
3. Type or paste the shortcode
4. Update/Publish

### Common Shortcodes

#### AJAX Search
```
[ajax_search placeholder="Search..." show_submit="true"]
```
Adds a live search bar.

#### Hero Slider
```
[hero_slider_query limit="3" autoplay="true"]
```
Displays hero slides from Hero Slides CPT.

#### Jobs Grid
```
[posts_grid post_type="job" limit="9" columns="3"]
```
Shows job postings in a grid.

#### Team Cards
```
[team_cards department="development" limit="8" columns="4"]
```
Displays team members.

#### Testimonials
```
[testimonials category="featured" limit="6" layout="carousel"]
```
Shows client testimonials.

#### FAQ Accordion
```
[faq_accordion category="general"]
```
Displays FAQs in accordion format.

#### Contact Form
```
[contact_form_7 id="123"]
```
Adds a contact form (requires Contact Form 7 plugin).

**For complete shortcode reference, see [SHORTCODES.md](./SHORTCODES.md)**

---

## 🏗️ Building Pages

### Creating a New Page

1. Go to **Pages → Add New**
2. Enter page title
3. Select page template (if needed):
   - Default Template
   - Full Width
   - Landing Page
4. Add content using blocks and shortcodes
5. Set featured image (optional)
6. Set page attributes:
   - **Parent**: Make this a sub-page
   - **Order**: Display order in menus
7. Click "Publish"

### Page Templates

**Default Template**
- Standard page with sidebar
- Good for: About, Contact, General pages

**Full Width**
- No sidebar, full content width
- Good for: Landing pages, Portfolios

**Landing Page**
- No header/footer (clean slate)
- Good for: Marketing campaigns

### Homepage Setup

1. Go to **Settings → Reading**
2. Select "A static page"
3. Choose Homepage: Select your home page
4. Choose Posts page: Select blog page (optional)
5. Click "Save Changes"

### Page Building Example: Homepage
```
<!-- Hero Section -->
[hero_slider_query limit="3" autoplay="true" delay="5000"]

<!-- Services Section -->
<h2>Our Services</h2>
[posts_grid post_type="services" limit="6" columns="3"]

<!-- Stats Section -->
[stats columns="4"]
  [stat number="500" suffix="+" label="Happy Clients"]
  [stat number="1000" suffix="+" label="Projects"]
  [stat number="50" suffix="" label="Team Members"]
  [stat number="15" suffix="+" label="Years Experience"]
[/stats]

<!-- Testimonials Section -->
<h2>What Clients Say</h2>
[testimonials category="featured" limit="6" layout="carousel"]

<!-- Call to Action -->
[modal button_text="Get Started" title="Contact Us"]
  [contact_form_7 id="123"]
[/modal]
```

---

## 🖼️ Media Management

### Uploading Media

**Method 1: Direct Upload**
1. Go to **Media → Add New**
2. Drag files or click "Select Files"
3. Wait for upload to complete
4. Files now available in Media Library

**Method 2: While Editing**
1. Click "Add Media" button in editor
2. Click "Upload Files"
3. Drag files or select
4. Insert into content

### Image Guidelines

**Recommended Sizes:**
- **Hero Images**: 1920x1080px
- **Featured Images**: 1200x800px
- **Thumbnails**: 400x300px
- **Team Photos**: 500x500px
- **Logos**: 200x100px

**File Formats:**
- **Photos**: JPG (smaller file size)
- **Graphics**: PNG (transparency support)
- **Logos**: SVG (scalable) or PNG
- **Icons**: SVG preferred

**Optimization:**
- Compress images before upload
- Max file size: 500KB for photos
- Use descriptive filenames
- Add alt text for accessibility

### Image Editing

1. Click image in Media Library
2. Click "Edit Image" button
3. Available actions:
   - Crop
   - Rotate
   - Flip
   - Scale
4. Click "Save"

### Adding Alt Text

**Why It's Important:**
- Screen readers for visually impaired
- SEO benefits
- Shows if image fails to load

**How to Add:**
1. Click image in Media Library
2. Find "Alternative Text" field (right sidebar)
3. Enter descriptive text
4. Example: "Team photo at company retreat 2024"
5. Click "Update"

### Organizing Media

**Using Folders** (if plugin installed):
1. Create folder structure
2. Drag media into folders
3. Easier to find files later

**Using Categories/Tags** (if enabled):
1. Edit media item
2. Add relevant categories/tags
3. Filter media library by category

---

## 🧭 Menus and Navigation

### Creating a Menu

1. Go to **Appearance → Menus**
2. Click "Create a new menu"
3. Enter menu name (e.g., "Primary Menu")
4. Click "Create Menu"

### Adding Menu Items

**Pages:**
1. Check pages to add (left sidebar)
2. Click "Add to Menu"

**Custom Links:**
1. Expand "Custom Links"
2. Enter URL and link text
3. Click "Add to Menu"

**Categories:**
1. Expand "Categories"
2. Check categories to add
3. Click "Add to Menu"

### Organizing Menu Items

**Reorder:**
- Drag items up or down

**Create Sub-menus:**
- Drag item slightly right under parent
- Indent = sub-menu

**Example Structure:**
```
Home
About
  - Our Team
  - Our Story
  - Careers
Services
  - Web Design
  - Development
  - Marketing
Portfolio
Contact
```

### Menu Settings

For each menu item, click arrow to expand:

- **Navigation Label**: Text shown to users
- **Title Attribute**: Tooltip on hover
- **CSS Classes**: Custom styling (advanced)
- **Link Relationship**: rel attribute
- **Open in new tab**: Check to open in new window

### Assigning Menu Location

1. Select "Primary Menu" or "Footer Menu" checkbox
2. Click "Save Menu"

---

## 🔍 SEO Best Practices

### Page Titles

**Good:**
- "Web Design Services | Your Company"
- "About Our Team - Your Company"

**Bad:**
- "Page 1"
- "Untitled"

### Meta Descriptions

If using Yoast SEO or similar:

1. Scroll to SEO section below editor
2. Enter description (155 characters max)
3. Include primary keyword
4. Make it compelling

**Example:**
"Professional web design services in Vienna. We create stunning, responsive websites that drive results. Get a free quote today!"

### Heading Structure

Use proper heading hierarchy:
```
H1 - Page Title (only one per page)
  H2 - Main Sections
    H3 - Subsections
      H4 - Details
```

**Example:**
```
H1: About Our Company
  H2: Our Mission
  H2: Our Team
    H3: Leadership Team
    H3: Development Team
  H2: Our Values
```

### Image Optimization

1. **Compress images** before upload
2. **Use descriptive filenames**: `team-photo-2024.jpg` not `IMG_1234.jpg`
3. **Add alt text** to every image
4. **Choose appropriate format**: JPG for photos, PNG for graphics

### Internal Linking

Link to related content on your site:

- From blog posts to services
- From service pages to portfolio
- From testimonials to contact page

**Benefits:**
- Helps users navigate
- Improves SEO
- Increases page views

### URL Structure

**Good URLs:**
- `yoursite.com/services/web-design`
- `yoursite.com/blog/seo-tips-2024`

**Bad URLs:**
- `yoursite.com/?p=123`
- `yoursite.com/page-1-copy-2`

**Tips:**
- Keep URLs short
- Use hyphens, not underscores
- Include keywords
- Avoid special characters

---

## 📋 Common Tasks

### Updating Existing Content

1. Find content in list view (Posts, Pages, etc.)
2. Click "Edit" link
3. Make changes
4. Click "Update" button

### Duplicating Content

If **Duplicate Post** plugin installed:

1. Hover over content in list
2. Click "Duplicate"
3. New draft created
4. Edit and publish

### Deleting Content

**Soft Delete (Trash):**
1. Click "Move to Trash"
2. Content moved to trash
3. Can be restored within 30 days

**Permanent Delete:**
1. Go to **Trash** tab
2. Click "Delete Permanently"
3. Cannot be undone!

### Bulk Actions

1. Check multiple items in list
2. Select action from "Bulk Actions" dropdown
3. Click "Apply"

**Available Actions:**
- Edit
- Move to Trash
- Change Category
- Mark as Featured

### Finding Content

**Search Bar:**
- Top right of screen
- Searches all content types

**Filters:**
- Date range
- Category
- Status (Published, Draft, etc.)
- Author

### Viewing Revisions

WordPress saves previous versions:

1. Edit post/page
2. Click "Revisions" in right sidebar
3. Use slider to view changes
4. Click "Restore This Revision" if needed

---

## 💡 Tips & Tricks

### Keyboard Shortcuts

| Action | Shortcut |
|--------|----------|
| Save Draft | Ctrl/Cmd + S |
| Bold Text | Ctrl/Cmd + B |
| Italic Text | Ctrl/Cmd + I |
| Insert Link | Ctrl/Cmd + K |
| Undo | Ctrl/Cmd + Z |
| Redo | Ctrl/Cmd + Shift + Z |

### Content Writing Tips

**Be Concise:**
- Short paragraphs (2-3 sentences)
- Use bullet points
- Break up long text with headings

**Use Active Voice:**
- Good: "We design websites"
- Bad: "Websites are designed by us"

**Include Calls to Action:**
- "Contact us today"
- "Learn more"
- "Get started"

### Image Tips

**Consistency:**
- Use similar style across site
- Same filters/editing style
- Consistent dimensions

**Brand Colors:**
- Use your brand colors
- Maintain color consistency
- Use branded templates

**Quality:**
- Use high-resolution images
- Avoid pixelated images
- Professional photography when possible

### Working with Drafts

**Auto-Save:**
- WordPress auto-saves every 60 seconds
- See "Last edited" timestamp
- Never lose your work

**Preview Changes:**
- Click "Preview" button
- See how content looks live
- Make adjustments before publishing

**Save Frequently:**
- Click "Save Draft" regularly
- Especially for long content
- Before taking breaks

### Mobile Preview

1. Click "Preview" dropdown
2. Select "Preview in new tab"
3. Use browser DevTools:
   - Press F12
   - Click device icon
   - Test different screen sizes

### Collaboration

**Comments:**
- Add notes for team members
- Discuss changes
- Leave feedback

**User Roles:**
- **Administrator**: Full access
- **Editor**: Edit all posts
- **Author**: Edit own posts
- **Contributor**: Submit for review
- **Subscriber**: Profile only

### Getting Help

**WordPress Resources:**
- WordPress.org Documentation
- WordPress Forums
- YouTube Tutorials

**Plugin Documentation:**
- Check plugin pages
- Read FAQs
- Contact support

**This System:**
- [SHORTCODES.md](./SHORTCODES.md) - Shortcode reference
- [AJAX-FILTERS.md](./AJAX-FILTERS.md) - Filter system
- [TROUBLESHOOTING.md](./TROUBLESHOOTING.md) - Common issues

---

## 🎓 Content Manager Checklist

### Before Publishing

- [ ] Proofread content for errors
- [ ] Check all links work
- [ ] Add alt text to all images
- [ ] Set featured image
- [ ] Choose appropriate categories/tags
- [ ] Add meta description (if SEO plugin)
- [ ] Preview on desktop and mobile
- [ ] Ensure proper heading structure
- [ ] Include call-to-action
- [ ] Schedule or publish

### Daily Tasks

- [ ] Check for new comments
- [ ] Review pending submissions
- [ ] Monitor site analytics
- [ ] Respond to contact forms
- [ ] Update time-sensitive content

### Weekly Tasks

- [ ] Publish new blog post
- [ ] Update job postings
- [ ] Check for broken links
- [ ] Review and update outdated content
- [ ] Backup important content

### Monthly Tasks

- [ ] Review site analytics
- [ ] Update team photos/info
- [ ] Refresh testimonials
- [ ] Update services/pricing
- [ ] Clean up media library
- [ ] Archive old content

---

## 📞 Getting Support

### Internal Support

- Contact your site administrator
- Check internal documentation
- Ask team members

### External Resources

- WordPress Support Forums
- Plugin documentation
- YouTube tutorials
- WordPress Facebook groups

### Emergency Contact

For urgent issues:
- Email: support@your-agency.com
- Phone: +XX XXX XXX XXXX
- Available: Mon-Fri, 9am-5pm

---

## 🎉 You're Ready!

You now have the knowledge to:
- Create and manage content
- Use custom post types
- Add shortcodes to pages
- Upload and manage media
- Build navigation menus
- Follow SEO best practices

**Start creating amazing content!** 🚀

---

**Last Updated:** February 2026  
**Version:** 1.0.0