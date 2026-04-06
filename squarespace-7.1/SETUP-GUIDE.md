# FluxOpenHome - Squarespace 7.1 Setup Guide

This guide walks you through building the FluxOpenHome website using Squarespace 7.1's visual editor with custom CSS and Code Blocks.

---

## Step 1: Choose a Template

In your Squarespace admin, pick a **clean, minimal template**. Recommended:
- **Brine** family (most flexible)
- **Bedford** family
- Any template with a clean header and good Commerce support

---

## Step 2: Add Custom CSS

1. Go to **Design > Custom CSS**
2. Paste the entire contents of `custom.css` from this folder
3. Click **Save**

This applies our full design system (colors, typography, cards, grids, buttons) across the site.

---

## Step 3: Add Code Injection (JavaScript)

1. Go to **Settings > Advanced > Code Injection**
2. In the **Footer** field, paste the contents of `code-injection-footer.html`
3. Click **Save**

This enables the FAQ accordion and smooth scrolling.

---

## Step 4: Upload Assets

1. Go to **Design > Custom CSS** and click the **Manage Custom Files** link
2. Upload all SVG files from the `../assets/` folder:
   - `placeholder-nightlight.svg`
   - `placeholder-irrigation.svg`
   - `placeholder-remote.svg`
   - `placeholder-gophr.svg`
   - `placeholder-enterprise.svg`
   - `placeholder-ios-app.svg`
   - `icon-homeassistant.svg`
   - `icon-esphome.svg`
   - `icon-github.svg`
   - `icon-matter.svg`
   - `icon-zigbee.svg`
3. After uploading, Squarespace gives each file a URL. You'll use these URLs in Code Blocks.
   - The URL format is: `https://static1.squarespace.com/static/YOUR_SITE_ID/filename.svg`

**Tip:** After uploading, you can reference files in Custom CSS like: `background-image: url(placeholder-nightlight.svg);` — Squarespace resolves uploaded file names automatically in the CSS editor.

---

## Step 5: Set Up Navigation

In **Pages > Main Navigation**, create this structure:

```
Products (link to /products)
Open Source (page)
For Business (page)
iOS App (page)
Support (page)
Store (link to /store)
```

---

## Step 6: Build Pages

### HOMEPAGE

1. Add a **Blank Section** with dark background
   - Set background to dark (use section styles: background color `#1a1a2e`)
   - Add a **Text Block**: heading "Open Source Smart Home Products"
   - Add a **Text Block**: subheading "Built on ESPHome. Designed for Home Assistant. Open to everyone."
   - Add two **Button Blocks**: "Explore Products" (link to /store) and "View on GitHub" (link to GitHub, secondary style)

2. Add a **Blank Section** (white background)
   - Add a **Text Block**: heading "Our Products", centered
   - Add a **Code Block** and paste the contents of `code-blocks/homepage-products.html`

3. Add a **Blank Section** (light background `#f8f9fa`)
   - Use two columns: left text, right icons
   - Left: heading "Built in the Open" + paragraph about open source + button "Our Open Source Repos"
   - Right: Add a **Code Block** with the protocol icons from `code-blocks/homepage-protocols.html`

4. Add a **Blank Section** (dark background `#16213e`)
   - Add badge "For Business"
   - Heading: "Professional Irrigation Management"
   - Description paragraph
   - Button: "Learn More" → /enterprise

### PRODUCTS / STORE PAGE

1. Go to **Pages > Not Linked > Products** (Squarespace auto-creates this)
2. Or add a new **Store Page**:
   - Title: "Store" or "Products"
   - Layout: Grid view
3. Add products in the Squarespace Commerce panel:

   | Product Name | Category (tag) | Price |
   |---|---|---|
   | Flux Night Light - Board Only | lighting | $24.99 |
   | Flux Night Light - Assembled | lighting | $39.99 |
   | Night Light Power Adapter | accessories | $9.99 |
   | Night Light Lamp Kit | accessories | $14.99 |
   | Irrigation Controller (Home Assistant) | irrigation | $89.99 |
   | Irrigation Controller (Direct Connect) | irrigation | $89.99 |
   | Irrigation Expansion Board | irrigation | $29.99 |
   | Controller Remote - M5Stack Dial | irrigation | $49.99 |
   | Controller Remote - CrowPanel | irrigation | $59.99 |
   | Gophr Soil Moisture Probe | sensors | $44.99 |

4. For each product, add:
   - Placeholder image (upload the matching SVG)
   - Description with features
   - Tags for category filtering

### OPEN SOURCE PAGE

1. Create a new **Page** titled "Open Source"
2. Add a **Blank Section** (dark background)
   - Heading: "Built in the Open"
   - Subheading about open source commitment
3. Add a **Blank Section** (light background)
   - Add a **Code Block** with `code-blocks/open-source-protocols.html`
4. Add a **Blank Section** (white)
   - Heading: "Our Repositories"
   - Add a **Code Block** with `code-blocks/open-source-repos.html`
5. Add a **Blank Section** (light)
   - Two columns: left text about HA integration, right HA icon
   - Feature bullet list

### FOR BUSINESS (ENTERPRISE) PAGE

1. Create a new **Page** titled "For Business"
2. **Dark hero section**: "Professional Irrigation Management"
3. **White section** with Code Block: `code-blocks/enterprise-paths.html` (two-path comparison)
4. **Light section** with Code Block: `code-blocks/enterprise-features.html` (feature grid)
5. **White section** with Code Block: `code-blocks/enterprise-steps.html` (how it works)
6. **Light section**: Direct Connect explanation (two columns)
7. **White section** with Code Block: `code-blocks/enterprise-pricing.html` (pricing table)
8. **Light section** with Code Block: `code-blocks/enterprise-trust.html` (open source commitment)

### iOS APP PAGE

1. Create a new **Page** titled "iOS App"
2. **Dark hero section** with two columns: left text, right app mockup image
3. **White section** with Code Block: `code-blocks/ios-app-features.html`
4. **Light section** with compatibility cards

### SUPPORT PAGE

1. Create a new **Page** titled "Support"
2. **Dark hero section**: "Support & Documentation"
3. **White section** with Code Block: `code-blocks/support-links.html`
4. **Light section** with Code Block: `code-blocks/support-faq.html`
5. **White section** with contact cards

---

## Step 7: Configure Commerce

1. Go to **Commerce > Payments** — connect Stripe or PayPal
2. Go to **Commerce > Shipping** — configure shipping rates
3. Go to **Commerce > Taxes** — set up tax rules
4. Go to **Commerce > Checkout** — customize checkout fields

---

## Step 8: Connect Domain

Your domain (fluxopenhome.com) should already be connected. Verify at:
**Settings > Domains & Email**

---

## File Reference

```
squarespace-7.1/
├── custom.css                      → Design > Custom CSS
├── code-injection-footer.html      → Settings > Code Injection > Footer
├── SETUP-GUIDE.md                  → This file
└── code-blocks/
    ├── homepage-products.html      → Homepage product grid
    ├── homepage-protocols.html     → Homepage protocol icons
    ├── open-source-protocols.html  → Open Source protocol grid
    ├── open-source-repos.html      → Open Source GitHub repos
    ├── enterprise-paths.html       → Enterprise two-path comparison
    ├── enterprise-features.html    → Enterprise feature grid
    ├── enterprise-steps.html       → Enterprise how-it-works steps
    ├── enterprise-pricing.html     → Enterprise pricing table
    ├── enterprise-trust.html       → Enterprise trust section
    ├── ios-app-features.html       → iOS App feature grid
    ├── support-links.html          → Support documentation links
    └── support-faq.html            → Support FAQ accordion
```
