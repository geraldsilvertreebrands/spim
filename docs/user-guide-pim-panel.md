# PIM Panel User Guide

**For**: PIM Editors and Administrators
**Panel URL**: `/pim`
**Last Updated**: 2025-12-14

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Dashboard Overview](#dashboard-overview)
3. [Managing Products](#managing-products)
4. [Managing Categories](#managing-categories)
5. [Working with Attributes](#working-with-attributes)
6. [Magento Sync](#magento-sync)
7. [Review Queue](#review-queue)
8. [Pipelines](#pipelines)
9. [Tips and Best Practices](#tips-and-best-practices)

---

## Getting Started

### Logging In

1. Navigate to your Silvertree platform URL
2. Enter your email and password
3. You'll be automatically redirected to the PIM panel at `/pim`

### Your Role

As a **PIM Editor** or **Admin**, you have access to:

- Product information management (CRUD operations)
- Category management
- Attribute and attribute section management
- Magento synchronization
- Review queue for approvals
- AI-powered pipelines for data enrichment

---

## Dashboard Overview

The PIM dashboard provides:

- **Magento Sync Stats**: View recent synchronization status, success rates, and errors
- **Quick Actions**: Links to common tasks like product creation, sync management
- **Recent Activity**: See what changed recently in the system

### Navigation

Use the sidebar to access:

- **Products**: View and edit all products
- **Categories**: Manage product categories
- **Attributes**: Define and manage product attributes
- **Attribute Sections**: Group attributes into logical sections
- **Entity Types**: Advanced - manage entity type definitions
- **Pipelines**: AI-powered data enrichment workflows
- **Users**: (Admin only) Manage user accounts and access
- **Magento Sync**: Synchronize data with Magento
- **Review Queue**: Approve or reject pending changes

---

## Managing Products

### Viewing Products

1. Click **Products** in the sidebar
2. You'll see a table with all products showing:
   - SKU
   - Name
   - Status (draft/published)
   - Creation date
   - Last modified date

### Filtering and Searching

- Use the **search bar** at the top to find products by name or SKU
- Click **Filters** to filter by:
  - Status (draft, published, discontinued)
  - Category
  - Brand
  - Date ranges

### Creating a New Product

1. Click **Products** → **New Product**
2. Fill in the required fields:
   - **SKU**: Unique product identifier (required)
   - **Name**: Product name (required)
   - **Status**: Draft or Published
3. Fill in optional attributes organized by sections:
   - **Basic Information**: Description, short description
   - **Pricing**: Cost, price, special price
   - **Inventory**: Stock status, quantity
   - **SEO**: Meta title, meta description, URL key
   - **Images**: Upload product images
   - **Categories**: Assign to one or more categories
4. Click **Create** to save

### Editing a Product

1. Click on a product row in the table
2. Modify any fields as needed
3. Click **Save** to update

### Side-by-Side Editing

For bulk editing:

1. Go to **Products**
2. Click **Side-by-Side Edit** (if available)
3. Edit multiple products at once in a split view
4. Changes are saved per product individually

### Deleting a Product

1. Open the product
2. Click **Delete** at the top right
3. Confirm deletion
4. **Note**: Deleted products cannot be recovered

---

## Managing Categories

### Viewing Categories

1. Click **Categories** in the sidebar
2. Categories are displayed in a tree structure showing parent-child relationships

### Creating a Category

1. Click **Categories** → **New Category**
2. Fill in:
   - **Name**: Category name (required)
   - **Parent Category**: Select a parent (optional - leave empty for top-level)
   - **Description**: Category description
   - **Status**: Active or Inactive
   - **URL Key**: SEO-friendly URL (auto-generated from name)
   - **Meta Title**: SEO meta title
   - **Meta Description**: SEO meta description
3. Click **Create**

### Editing a Category

1. Click on a category
2. Modify fields as needed
3. Click **Save**

### Moving Categories

To move a category to a different parent:

1. Edit the category
2. Change the **Parent Category** dropdown
3. Click **Save**

### Deleting a Category

1. Open the category
2. Click **Delete**
3. Confirm deletion
4. **Note**: Products in the deleted category will NOT be deleted, but will be uncategorized

---

## Working with Attributes

### What are Attributes?

Attributes define the fields available for products (e.g., "Color", "Brand", "Material"). Each attribute has:

- **Name**: The attribute identifier (e.g., `color`)
- **Label**: Display name (e.g., "Product Color")
- **Type**: Data type (text, select, multiselect, integer, boolean, etc.)
- **Validation**: Rules like required, unique, min/max length
- **Options**: For select/multiselect attributes

### Viewing Attributes

1. Click **Attributes** in the sidebar
2. See all attributes with their type, label, and validation rules

### Creating an Attribute

1. Click **Attributes** → **New Attribute**
2. Fill in:
   - **Name**: Machine-readable name (lowercase, underscores) - e.g., `brand_name`
   - **Label**: User-friendly label - e.g., "Brand Name"
   - **Type**: Select from:
     - Text (single line)
     - Textarea (multi-line)
     - Select (dropdown)
     - Multiselect (multiple choices)
     - Integer
     - Decimal
     - Boolean (yes/no)
     - Date
     - JSON
     - HTML (rich text)
     - BelongsTo (relationship to another entity)
     - BelongsToMulti (relationship to multiple entities)
   - **Validation Rules**: Add rules like:
     - Required
     - Unique
     - Min/Max length
     - Min/Max value (for numbers)
     - Email format
     - URL format
   - **Options**: For select/multiselect, add available choices
   - **Attribute Section**: Assign to a logical grouping
3. Click **Create**

### Editing an Attribute

1. Click on an attribute
2. Modify fields
3. Click **Save**
4. **Warning**: Changing attribute type can cause data loss. Be careful!

### Deleting an Attribute

1. Open the attribute
2. Click **Delete**
3. Confirm deletion
4. **Warning**: All product data for this attribute will be lost!

---

## Attribute Sections

### What are Attribute Sections?

Attribute sections group related attributes together for better organization in forms. For example:

- **Basic Information**: Name, SKU, Description
- **Pricing**: Cost, Price, Special Price
- **SEO**: Meta Title, Meta Description, URL Key

### Creating an Attribute Section

1. Click **Attribute Sections** → **New Attribute Section**
2. Fill in:
   - **Name**: Section identifier (e.g., `pricing`)
   - **Label**: Display name (e.g., "Pricing Information")
   - **Sort Order**: Order of appearance in forms (lower = first)
3. Click **Create**

### Assigning Attributes to Sections

When creating or editing an attribute, select the **Attribute Section** dropdown.

---

## Magento Sync

The PIM system can synchronize product data with Magento e-commerce platforms.

### Starting a Sync

1. Click **Magento Sync** in the sidebar
2. Choose sync direction:
   - **From Magento**: Import products from Magento into PIM
   - **To Magento**: Export products from PIM to Magento
   - **Bidirectional**: Sync both ways (merge changes)
3. Select what to sync:
   - Products
   - Categories
   - Attributes
   - Attribute options
4. Click **Start Sync**

### Monitoring Sync Progress

- The sync runs in the background
- View progress in the **Magento Sync** page
- See stats:
  - Total records processed
  - Created
  - Updated
  - Deleted
  - Failed
  - Conflicts (bidirectional only)

### Viewing Sync Details

1. Click on a sync run to see detailed results
2. View each sync operation:
   - Entity type (product, category, etc.)
   - Operation (create, update, delete, conflict)
   - Status (success, failed)
   - Error message (if failed)

### Resolving Conflicts

For bidirectional sync, conflicts may occur when both systems have changes:

1. Go to **Review Queue**
2. See conflicting records
3. Choose which version to keep:
   - Accept Magento version
   - Keep PIM version
   - Manually merge

---

## Review Queue

The Review Queue is where you approve changes that require review before being published.

### Accessing the Review Queue

1. Click **Review Queue** in the sidebar
2. See all pending reviews grouped by:
   - New products awaiting approval
   - Modified products
   - Sync conflicts

### Approving a Change

1. Click on a pending review
2. Review the proposed changes
3. Click **Approve** to accept
4. The change will be applied immediately

### Rejecting a Change

1. Click on a pending review
2. Click **Reject**
3. Optionally add a comment explaining why
4. The change will be discarded

---

## Pipelines

Pipelines are AI-powered workflows that automatically enrich product data.

### What are Pipelines?

Pipelines use AI (OpenAI GPT) to:

- Generate product descriptions from specifications
- Extract attributes from raw text
- Translate content
- Classify products into categories
- Validate data quality

### Viewing Pipelines

1. Click **Pipelines** in the sidebar
2. See all configured pipelines with:
   - Name
   - Entity type (products, categories, etc.)
   - Modules (processing steps)
   - Schedule (manual, nightly, on-save)

### Creating a Pipeline

1. Click **Pipelines** → **New Pipeline**
2. Fill in:
   - **Name**: Pipeline identifier
   - **Entity Type**: What this pipeline processes
   - **Schedule**: When to run (manual, nightly, on-save)
   - **Entity Filter**: Optional - only process matching entities
3. Add **Modules** (processing steps):
   - **Attribute Source**: Read data from attributes
   - **AI Prompt Processor**: Use GPT to transform data
   - **Calculation Processor**: Perform calculations
   - **Attribute Writer**: Write results to attributes
4. Click **Create**

### Running a Pipeline

**Manual Pipelines:**
1. Go to **Pipelines**
2. Click on a pipeline
3. Click **Run Now**
4. Select entities to process (or all)
5. Click **Start**

**Scheduled Pipelines:**
- Nightly pipelines run automatically at midnight
- On-save pipelines run whenever a product is saved

### Viewing Pipeline Results

1. Click on a pipeline
2. Go to the **Runs** tab
3. Click on a run to see:
   - Entities processed
   - Success/failure status
   - Processing time
   - Output data

### Pipeline Evaluations

Pipelines can have evaluations (quality checks):

1. Edit a pipeline
2. Go to **Evaluations** tab
3. Add evaluation criteria (e.g., "Description must be > 100 chars")
4. View evaluation results in pipeline runs

---

## Tips and Best Practices

### Product Management

- **Use consistent SKU formats**: Define a SKU naming convention (e.g., `BRAND-CATEGORY-NNN`)
- **Fill in all required attributes**: This improves searchability and data quality
- **Use categories wisely**: Don't over-categorize - keep it simple
- **Add images**: Products with images perform better

### Attribute Design

- **Don't create duplicate attributes**: Check if an attribute already exists before creating
- **Use validation rules**: This prevents bad data from entering the system
- **Group attributes logically**: Use attribute sections to organize forms
- **Use select over text when possible**: This ensures data consistency

### Syncing with Magento

- **Test with a small batch first**: Before syncing all products, test with 10-20
- **Schedule nightly syncs**: Avoid running large syncs during business hours
- **Monitor for conflicts**: Check the review queue daily for bidirectional sync conflicts
- **Keep Magento as source of truth for pricing**: Don't override prices in PIM if Magento is authoritative

### Working with Pipelines

- **Start simple**: Create a basic pipeline first, then add complexity
- **Test on a single product**: Before running on all products, test on one
- **Monitor costs**: AI pipelines use OpenAI API which has usage costs
- **Add evaluations**: Quality checks help catch bad AI outputs

### Performance

- **Use filters**: When viewing large product lists, filter to reduce load time
- **Avoid massive bulk edits**: Edit in batches of 50-100 products max
- **Close unused tabs**: Each open PIM page maintains a database connection

---

## Need Help?

- **Troubleshooting**: See [Troubleshooting Guide](troubleshooting-guide.md)
- **FAQ**: See [Frequently Asked Questions](faq.md)
- **Admin Tasks**: See [Admin Guide](admin-guide.md)
- **Support**: Contact your system administrator or support@silvertreebrands.com

---

**Document Version**: 1.0
**Last Updated**: 2025-12-14
**Platform Version**: Laravel 12 + Filament 4
