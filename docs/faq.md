# Frequently Asked Questions (FAQ)

**Platform**: Silvertree Multi-Panel Platform
**Last Updated**: 2025-12-14

---

## Table of Contents

1. [General Questions](#general-questions)
2. [Account and Access](#account-and-access)
3. [PIM Panel](#pim-panel)
4. [Supply Panel](#supply-panel)
5. [Pricing Panel](#pricing-panel)
6. [Data and Reporting](#data-and-reporting)
7. [Billing and Subscriptions](#billing-and-subscriptions)

---

## General Questions

### What is the Silvertree Multi-Panel Platform?

The Silvertree Multi-Panel Platform is an integrated system providing:

- **PIM Panel**: Product Information Management for internal teams
- **Supply Panel**: Analytics and insights for suppliers and brand managers
- **Pricing Panel**: Competitor price tracking and margin analysis

It serves Silvertree Brands companies: Faithful to Nature, Pet Heaven, and UCOOK.

### Which panel should I use?

Depends on your role:

- **PIM Panel**: If you manage product data (internal staff)
- **Supply Panel**: If you're a supplier tracking your brand's performance
- **Pricing Panel**: If you manage pricing and competitor analysis

Ask your administrator if you're unsure.

### Can I access multiple panels?

Yes, if you have multiple roles assigned. Admins can access all three panels.

### Is my data secure?

Yes. The platform uses:

- Encrypted HTTPS connections
- Role-based access control
- Session-based authentication
- Regular security audits
- Brand isolation (suppliers only see their own brands)

### How often is data updated?

- **Sales data**: Nightly (every 24 hours)
- **Inventory data**: Every 6 hours
- **Forecasts**: Weekly
- **Competitor prices**: Depends on import frequency (typically weekly)

The last update timestamp is shown on each page.

### Can I use this on mobile?

Yes, the platform is mobile-responsive. However, for best experience we recommend:

- Tablet or larger for charts and tables
- Desktop for data entry and bulk editing

---

## Account and Access

### How do I get an account?

Contact your Silvertree Brands administrator or email support@silvertreebrands.com.

### I forgot my password. What do I do?

1. Go to the login page
2. Click "Forgot Password"
3. Enter your email
4. Check your email for a password reset link
5. Follow the link to set a new password

### How do I change my password?

1. Log in
2. Click your name in the top right
3. Select "Profile"
4. Click "Change Password"
5. Enter current and new password
6. Save

### What are the password requirements?

- Minimum 8 characters
- Recommended: Mix of upper/lower case, numbers, and symbols

### My account says "Inactive". What happened?

Your account has been deactivated by an administrator. Contact your admin or support@silvertreebrands.com to request reactivation.

### Can I have multiple email addresses on one account?

No. Each account has one email address. If you need to change your email, contact your administrator.

### What's the difference between Basic and Premium supplier tiers?

**Basic Tier** includes:
- Dashboard KPIs
- Products page
- Trends analysis
- Benchmarks

**Premium Tier** adds:
- Forecasting
- Cohort analysis
- RFM segmentation
- Retention tracking
- Product deep dive
- Supply chain metrics
- Purchase order tracking
- Customer engagement
- Market share
- Marketing analytics

Contact sales@silvertreebrands.com to upgrade.

---

## PIM Panel

### What is PIM?

PIM = Product Information Management. It's a centralized system for managing all product data: names, descriptions, attributes, images, pricing, inventory, etc.

### Who can access the PIM panel?

Users with the `admin` or `pim-editor` role.

### Can I bulk edit products?

Yes. Use the Side-by-Side Edit feature to edit multiple products at once.

### How do I add a new product?

1. Go to PIM → Products
2. Click "New Product"
3. Fill in required fields (SKU, Name)
4. Fill in additional attributes
5. Click "Create"

### Can I import products from a CSV?

Not currently available via UI. Contact your administrator for bulk imports.

### What's the difference between a Product and a Category?

- **Product**: Individual item for sale (e.g., "Organic Coconut Oil 500ml")
- **Category**: Grouping of products (e.g., "Oils & Fats")

Products can belong to multiple categories.

### What are Attributes?

Attributes are fields that describe products. Examples:

- Text: "Brand Name"
- Select: "Size" (Small, Medium, Large)
- Number: "Weight in grams"
- Boolean: "Organic" (Yes/No)

You can create custom attributes to fit your needs.

### Can I sync with Magento?

Yes. Go to PIM → Magento Sync. You can sync:

- From Magento to PIM (import)
- From PIM to Magento (export)
- Bidirectional (both ways)

### What are Pipelines?

Pipelines are AI-powered workflows that automatically enrich product data. For example:

- Generate descriptions from specifications
- Extract attributes from text
- Classify products into categories
- Translate content

### How much do Pipelines cost?

Pipelines use OpenAI API which has usage-based pricing. Contact your administrator for cost details.

---

## Supply Panel

### What data does the Supply Panel show?

Sales and performance data for your brand(s), including:

- Revenue, units sold, customers
- Sales trends over time
- Product performance
- Benchmarks vs competitors
- Forecasts (Premium only)
- Customer retention (Premium only)

### Where does the data come from?

Data comes from BigQuery, which aggregates:

- E-commerce orders from Magento/website
- Inventory levels
- Customer data

All data is from Silvertree Brands' internal systems.

### Can I see my competitors' data?

No. You can only see:

- Your own brand's performance
- Anonymized category benchmarks (e.g., "Category average sales growth")

You cannot see specific competitor sales or customer data.

### Why does my dashboard show "No Data"?

Common reasons:

1. **No brand access**: Contact admin to assign brands
2. **Date range too narrow**: Try "Last 90 Days"
3. **No sales in period**: Your brand may not have sales in the selected period
4. **Brand filter**: Try "All Brands" if you have multiple

### Can I export data?

Yes. Every page has an "Export" button. You can download data as CSV or Excel.

### What's the difference between "vs Previous Period" and "vs Last Year"?

- **vs Previous Period**: Compares to the equivalent previous period (e.g., this month vs last month)
- **vs Last Year**: Compares to the same period last year (e.g., Dec 2024 vs Dec 2023)

Use "vs Last Year" to account for seasonality.

### What is RFM Analysis? (Premium)

RFM segments customers by:

- **Recency**: When did they last buy?
- **Frequency**: How often do they buy?
- **Monetary**: How much do they spend?

This helps you identify:

- Champions (best customers)
- At Risk (used to buy, now lapsed)
- Lost (haven't bought in a long time)

Use segments for targeted marketing.

### What is a Cohort? (Premium)

A cohort is a group of customers who first purchased in the same month. Cohort analysis tracks:

- What % come back in month 2, 3, 4, etc.
- Which months acquire the best customers
- When customers typically churn

### How accurate are forecasts? (Premium)

Forecasts use historical data and statistical models. Accuracy depends on:

- Data history (more data = better forecasts)
- Seasonality
- Promotions and events
- Market changes

Forecasts include confidence intervals (best case, expected, worst case).

---

## Pricing Panel

### What data does the Pricing Panel show?

Competitor prices for your products, including:

- Current competitor prices
- Price history over time
- Price comparison matrix
- Margin analysis
- Price alerts

### Where do competitor prices come from?

Competitor prices are imported from external sources via:

- CSV uploads
- API integrations

Actual web scraping is not done by this platform.

### How often are competitor prices updated?

Depends on import frequency. Typically:

- Manual CSV: As often as you upload
- API: Usually weekly or nightly (check with admin)

Last update time is shown on each page.

### Can I track any competitor?

Yes, as long as you can provide their price data. Common competitors:

- Takealot
- Wellness Warehouse
- Checkers
- Pick n Pay

### What are Price Alerts?

Alerts notify you when:

- Competitor beats your price
- Price drops below a threshold
- Price changes by more than X%
- Competitor goes out of stock

You receive alerts via email and in-app notifications.

### How do I set up a Price Alert?

1. Go to Pricing → Price Alerts
2. Click "New Alert"
3. Select product (or leave blank for all)
4. Select competitor (or leave blank for any)
5. Choose alert type
6. Set threshold
7. Activate alert

### What is Margin Analysis?

Margin analysis shows:

- Your cost
- Your price
- Gross margin %
- Competitor average price
- Margin if you matched competitor price

This helps you optimize pricing while maintaining profitability.

### Can I import historical price data?

Yes, via CSV upload. Include a `scraped_at` column with historical dates.

### Why is a product not showing in the Pricing Panel?

Possible reasons:

1. **No price data imported**: Import CSV with price data
2. **Product not in PIM**: Add product to PIM first
3. **SKU mismatch**: Ensure CSV SKU matches PIM SKU

---

## Data and Reporting

### Can I export data?

Yes. All tables and charts have an "Export" button. Export formats:

- CSV (for Excel, Google Sheets)
- Excel (.xlsx)

### Are exports real-time?

Exports reflect the current data at the time of export. Data freshness depends on the panel:

- Supply: Nightly updates
- Pricing: Depends on import frequency

### Can I schedule automated reports?

Not currently available. You can manually export data on a schedule.

### Can I create custom reports?

Not via the UI. Contact your administrator for custom reporting needs.

### What's the maximum export size?

No hard limit, but large exports (> 10,000 rows) may take time. Consider filtering data first.

### Can I share data with my team?

Yes. Export data and share the file. Ensure you comply with any data privacy policies.

### Can I integrate with other tools (Power BI, Tableau)?

Not directly. You can:

1. Export data to CSV/Excel
2. Import into Power BI/Tableau
3. Refresh manually as needed

API integration may be available in the future.

---

## Billing and Subscriptions

### How much does it cost?

Pricing varies by panel and tier. Contact sales@silvertreebrands.com for details.

### How do I upgrade to Premium (Supply Panel)?

Contact your account manager or email sales@silvertreebrands.com.

### What happens if I downgrade from Premium to Basic?

You'll lose access to Premium features:

- Forecasting
- Cohorts
- RFM Analysis
- Retention
- Product Deep Dive
- etc.

Your data is retained, so if you upgrade again, it will still be available.

### Can I cancel my account?

Contact your administrator or support@silvertreebrands.com.

### Is there a free trial?

Contact sales@silvertreebrands.com to inquire about trials.

### Do you offer training?

Yes. See [Training Materials](user-guide-pim-panel.md#tips-and-best-practices) or contact support@silvertreebrands.com to schedule training.

---

## Still Have Questions?

**For General Inquiries**:
- Email: support@silvertreebrands.com

**For Sales and Upgrades**:
- Email: sales@silvertreebrands.com

**For Technical Issues**:
- See [Troubleshooting Guide](troubleshooting-guide.md)
- Contact your administrator
- Email: support@silvertreebrands.com

**For Admin/Technical Documentation**:
- [Admin Guide](admin-guide.md)
- [Multi-Panel Architecture](multi-panel-architecture-overview.md)
- [API Documentation](api-documentation.md)

---

**Document Version**: 1.0
**Last Updated**: 2025-12-14
**Platform Version**: Laravel 12 + Filament 4
