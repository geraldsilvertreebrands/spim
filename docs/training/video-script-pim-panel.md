# PIM Panel Video Walkthrough Script

**Duration**: ~10 minutes
**Target Audience**: PIM Editors and Administrators
**Last Updated**: 2025-12-14

---

## Video Structure

1. Introduction (0:00-0:30)
2. Dashboard Tour (0:30-2:00)
3. Managing Products (2:00-5:00)
4. Attributes and Categories (5:00-7:00)
5. Magento Sync (7:00-8:30)
6. AI Pipelines (8:30-9:30)
7. Conclusion (9:30-10:00)

---

## Script

### 1. Introduction (0:00-0:30)

**[SCREEN: Login page]**

> "Welcome to the Silvertree PIM Panel training. I'm [Name], and in this 10-minute video, I'll show you how to use the Product Information Management system to manage your product catalog."

**[ACTION: Log in as admin@silvertreebrands.com]**

> "Let's get started by logging in. After authentication, you'll be redirected to the PIM dashboard."

---

### 2. Dashboard Tour (0:30-2:00)

**[SCREEN: PIM Dashboard]**

> "This is your PIM dashboard. On the left sidebar, you'll see the main navigation:"

**[ACTION: Hover over each menu item]**

> "- **Products**: View and edit all products
> - **Categories**: Organize products into categories
> - **Attributes**: Define product fields like 'Brand', 'Color', 'Size'
> - **Attribute Sections**: Group attributes for better organization
> - **Pipelines**: AI-powered data enrichment
> - **Users**: Manage user accounts (admins only)
> - **Magento Sync**: Synchronize with Magento e-commerce platform"

**[ACTION: Point to top right]**

> "In the top right, you'll see your name and the panel switcher. As an admin, I can switch between PIM, Supply, and Pricing panels."

**[ACTION: Click on Magento Sync Stats widget]**

> "The dashboard shows Magento sync statistics—successful syncs, recent errors, and sync history."

---

### 3. Managing Products (2:00-5:00)

**[SCREEN: Products list]**

**[ACTION: Click Products in sidebar]**

> "Let's look at product management. Here's the product list showing all your products with SKU, name, status, and dates."

**[ACTION: Use search bar]**

> "You can search by product name or SKU. Let me search for 'coconut'..."

**[SCREEN: Search results]**

> "Great! Here are all coconut-related products."

**[ACTION: Click on a product]**

**[SCREEN: Edit Product form]**

> "Clicking a product opens the edit form. Notice how attributes are organized into sections:"

**[ACTION: Scroll through sections]**

> "- **Basic Information**: Name, SKU, Description
> - **Pricing**: Cost, Price, Special Price
> - **Inventory**: Stock quantity, stock status
> - **SEO**: Meta title, meta description, URL key
> - **Images**: Product photos
> - **Categories**: Product categorization"

**[ACTION: Edit the name field]**

> "I'll make a small edit to the product name..."

**[ACTION: Click Save]**

> "And save. That's it! The change is immediately saved to the database."

**[ACTION: Click back arrow]**

**[SCREEN: Products list]**

**[ACTION: Click 'New Product']**

> "Creating a new product is simple. Click 'New Product'."

**[SCREEN: Create Product form]**

> "Fill in the required fields—SKU and Name—then add any additional attributes you need."

**[ACTION: Fill in SKU and Name]**

> "Let's create a demo product... SKU 'DEMO-001', Name 'Demo Product'."

**[ACTION: Click Create]**

> "Click Create, and you're done! Your product is now in the system."

---

### 4. Attributes and Categories (5:00-7:00)

**[SCREEN: Attributes list]**

**[ACTION: Click Attributes in sidebar]**

> "Attributes define the fields available on your products. Here you can see all attributes with their type and validation rules."

**[ACTION: Click on an attribute]**

**[SCREEN: Edit Attribute form]**

> "Each attribute has:
> - **Name**: Machine-readable identifier
> - **Label**: User-friendly display name
> - **Type**: Text, Select, Number, etc.
> - **Validation Rules**: Required, unique, min/max length
> - **Options**: For select fields, the available choices"

**[ACTION: Navigate to Attribute Sections]**

**[SCREEN: Attribute Sections list]**

> "Attribute sections group related attributes together. For example, all pricing-related attributes go in the 'Pricing' section."

**[ACTION: Click Categories]**

**[SCREEN: Categories list]**

> "Categories organize products hierarchically. You can create parent categories like 'Food' and child categories like 'Organic Food'."

**[ACTION: Click on a category]**

> "Each category has a name, description, parent category, and SEO fields."

---

### 5. Magento Sync (7:00-8:30)

**[SCREEN: Magento Sync page]**

**[ACTION: Click Magento Sync in sidebar]**

> "The Magento Sync feature synchronizes product data between PIM and Magento."

**[ACTION: Click 'New Sync']**

> "To start a sync, click 'New Sync' and choose your sync direction:"

**[SCREEN: Sync configuration modal]**

> "- **From Magento**: Import products from Magento into PIM
> - **To Magento**: Export products from PIM to Magento
> - **Bidirectional**: Sync both ways, with conflict detection"

**[ACTION: Select 'From Magento']**

> "Let's do a Magento import. Select what to sync—products, categories, attributes—and click 'Start Sync'."

**[ACTION: Click 'Start Sync']**

**[SCREEN: Sync in progress]**

> "The sync runs in the background. You'll see progress in real-time."

**[SCREEN: Sync completed]**

> "Once complete, you can view detailed results showing created, updated, and failed records."

**[ACTION: Click on a sync run]**

**[SCREEN: Sync details]**

> "Here's a detailed log of every operation. If there are errors, you can see exactly what went wrong and fix it."

---

### 6. AI Pipelines (8:30-9:30)

**[SCREEN: Pipelines list]**

**[ACTION: Click Pipelines in sidebar]**

> "Pipelines are AI-powered workflows that automatically enrich product data using GPT."

**[ACTION: Click on a pipeline]**

**[SCREEN: Pipeline detail]**

> "Each pipeline has modules that process data step by step. For example, this pipeline:
> 1. Reads product specifications
> 2. Sends them to GPT to generate a description
> 3. Writes the generated description back to the product"

**[ACTION: Click 'Run Now']**

**[SCREEN: Run pipeline modal]**

> "You can run pipelines manually on selected products or schedule them to run nightly."

**[ACTION: Select a few products, click 'Start']**

> "Let's run this on a few products..."

**[SCREEN: Pipeline running]**

> "The pipeline processes each product. You'll see progress and results in real-time."

**[ACTION: Click on a completed run]**

> "Here's the output. The pipeline successfully generated descriptions for all products!"

---

### 7. Conclusion (9:30-10:00)

**[SCREEN: Dashboard]**

> "That covers the basics of the PIM panel! To recap:
> - **Products**: Create, edit, and search products
> - **Attributes & Categories**: Define your product schema
> - **Magento Sync**: Synchronize with your e-commerce platform
> - **Pipelines**: Use AI to enrich product data automatically"

> "For more detailed information, check out the PIM Panel User Guide in the documentation."

> "If you have questions, contact support@silvertreebrands.com. Thanks for watching!"

**[SCREEN: Fade out]**

---

## Post-Production Notes

### Visual Overlays

Add text overlays for:
- Key terms (first mention): "PIM", "SKU", "Attribute", "Pipeline"
- Important URLs: support email, documentation links

### Callouts

Use arrows or highlights for:
- Navigation menu items
- Action buttons (Save, Create, etc.)
- Important fields (SKU, Name)

### Cuts

Consider jump cuts for:
- Long-running syncs (show start, then cut to completion)
- Large forms (show key fields, skip repetitive sections)

### Background Music

- Soft, corporate background music (low volume)
- Mute during critical explanations

### Captions

- Enable auto-captions for accessibility
- Review and correct auto-generated text

---

## Recording Tips

1. **Resolution**: Record in 1080p minimum (1920x1080)
2. **Browser zoom**: Set to 100% for clarity
3. **Test data**: Use realistic but obviously fake data
4. **Clear cache**: Start with fresh session for consistent UI
5. **Practice**: Do a dry run before recording
6. **Pace**: Speak slowly and clearly—pause between sections
7. **Mouse**: Move cursor deliberately, avoid rapid movements

---

## Platform Specifics

**Recording Software Recommendations**:
- **Mac**: QuickTime, ScreenFlow, or Camtasia
- **Windows**: OBS Studio, Camtasia, or Snagit
- **Online**: Loom (easiest, cloud-based)

**Microphone**: Use headset or USB mic, not laptop mic

**Environment**: Quiet room, no background noise

---

## Distribution

Once recorded:
1. Upload to company video platform (Vimeo, YouTube unlisted, or LMS)
2. Embed in internal training portal
3. Link from docs/user-guide-pim-panel.md
4. Include in onboarding materials

---

**Document Version**: 1.0
**Last Updated**: 2025-12-14
