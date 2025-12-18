# Pricing Panel User Guide

**For**: Pricing Analysts and Managers
**Panel URL**: `/pricing`
**Last Updated**: 2025-12-14

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Dashboard Overview](#dashboard-overview)
3. [Competitor Prices](#competitor-prices)
4. [Price History](#price-history)
5. [Price Comparison Matrix](#price-comparison-matrix)
6. [Margin Analysis](#margin-analysis)
7. [Price Alerts](#price-alerts)
8. [Importing Price Data](#importing-price-data)
9. [Exporting Data](#exporting-data)
10. [Tips and Best Practices](#tips-and-best-practices)

---

## Getting Started

### Logging In

1. Navigate to your Silvertree platform URL
2. Enter your email and password
3. You'll be automatically redirected to the Pricing panel at `/pricing`

### Your Role

As a **Pricing Analyst** or **Admin**, you have access to:

- Competitor price tracking
- Price history analysis
- Price comparison tools
- Margin analysis
- Automated price alerts
- Price data import tools

---

## Dashboard Overview

The Pricing dashboard provides a high-level view of your pricing landscape.

### Key Metrics

- **Products Tracked**: Number of products with competitor price data
- **Average Price Position**: How you rank vs competitors (% cheaper/more expensive)
- **Recent Price Changes**: Count of competitor price changes this week
- **Active Alerts**: Number of price alerts currently triggered

### Price Position Chart

A histogram showing:

- How many products you're cheapest on
- How many products you're in the middle
- How many products you're most expensive on

### Recent Price Changes

Table showing the most recent competitor price changes:

- Product name
- Competitor
- Old price
- New price
- Change %
- Date

---

## Competitor Prices

View current competitor prices for all tracked products.

### Table Columns

- **Product Name**: Your product name and SKU
- **Our Price**: Your current selling price
- **Competitor 1 Price**: e.g., Takealot price
- **Competitor 2 Price**: e.g., Wellness Warehouse price
- **Competitor 3 Price**: e.g., Checkers price
- **Price Position**: Where you rank (Cheapest, Mid-range, Most Expensive)
- **Last Updated**: When competitor data was last scraped

### Color Coding

- **Green**: You're the cheapest
- **Yellow**: You're mid-range
- **Red**: You're the most expensive

### Filtering

Filter by:

- **Category**: Show only specific product categories
- **Price Position**: Show only products where you're most expensive
- **Competitor**: Show only products tracked by a specific competitor
- **Last Updated**: Show only recently updated prices

### Sorting

Click column headers to sort by:

- Biggest price difference
- Most expensive competitors
- Recently updated

### Product Actions

Click on a product to:

- View detailed price history
- Set up a price alert
- Update your own price (links to PIM if you have access)

---

## Price History

Track how competitor prices have changed over time.

### Viewing Price History

1. Go to **Price History**
2. Search for or select a product
3. View the price trend chart

### Chart Features

The price history chart shows:

- **Your Price**: Solid line (your price over time)
- **Competitor Prices**: Dashed lines (one per competitor)
- **Time Range**: Zoom to last 7/30/90/365 days or custom range
- **Price Points**: Hover to see exact price and date

### Identifying Trends

Look for:

- **Competitor price drops**: Opportunity to match or beat
- **Your price vs market**: Are you consistently higher/lower?
- **Seasonal patterns**: Prices that change during holidays or seasons
- **Promotional periods**: Temporary price dips

### Exporting History

Export price history data as CSV for deeper analysis in Excel.

---

## Price Comparison Matrix

Side-by-side comparison of prices across all competitors.

### Matrix View

A table showing:

```
Product Name          | Us    | Takealot | Wellness | Checkers
----------------------|-------|----------|----------|----------
Organic Coconut Oil   | R89   | R85      | R92      | R95
Manuka Honey 500g     | R350  | R345     | R360     | R355
...
```

### Color Legend

- **Green**: Cheapest price in the row
- **Red**: Most expensive price in the row
- **Yellow**: Mid-range price

### Filters

- **Category**: Show only products in a category
- **Show Only Where We're Most Expensive**: Focus on repricing opportunities

### Using the Matrix

**To identify repricing opportunities:**

1. Filter to "Show Only Where We're Most Expensive"
2. Sort by "Price Difference"
3. Review top products where you're significantly more expensive
4. Decide whether to lower price, justify premium, or discontinue

**To monitor market trends:**

1. View all products in a category
2. Identify general market price levels
3. Spot outliers (unusually high/low)

---

## Margin Analysis

Understand profitability by comparing costs, prices, and competitor prices.

### Margin Metrics

- **Cost**: Your product cost
- **Selling Price**: Your current price
- **Gross Margin**: (Price - Cost) / Price × 100%
- **Margin $**: Dollar profit per unit
- **Competitor Avg Price**: Average competitor price
- **Market Margin**: If you matched competitor avg, what would margin be?

### Table Columns

- Product Name
- Cost
- Our Price
- Gross Margin %
- Competitor Avg Price
- Margin if Matched

### Color Coding

- **Green**: Margin > 30% (healthy)
- **Yellow**: Margin 15-30% (moderate)
- **Red**: Margin < 15% (low)

### Filtering

- **Category**: Focus on specific categories
- **Margin Range**: Show only low-margin or high-margin products
- **Price Position**: Show only where you're cheapest/most expensive

### Margin Insights

The system provides insights like:

- "You could increase price by 5% and still be cheapest"
- "Matching competitor price would reduce margin to 12%"
- "This product has negative margin - selling at a loss"

### Using Margin Analysis

**To optimize pricing:**

1. Identify products with high margin + cheapest position
   - → Consider raising price
2. Identify products with low margin + most expensive position
   - → Consider lowering cost, raising efficiency, or discontinuing
3. Identify products with healthy margin + mid-range price
   - → Maintain current pricing

---

## Price Alerts

Set up automated alerts to be notified of important price changes.

### Alert Types

1. **Competitor Beats Our Price**: Alerted when a competitor drops below your price
2. **Price Drops Below Threshold**: Alerted when any price drops below a set amount
3. **Price Change > X%**: Alerted when competitor changes price by more than X%
4. **Out of Stock**: Alerted when a competitor goes out of stock

### Creating an Alert

1. Go to **Price Alerts**
2. Click **New Alert**
3. Fill in:
   - **Product**: Which product to track (or leave blank for all)
   - **Competitor**: Which competitor (or leave blank for any)
   - **Alert Type**: Select from dropdown
   - **Threshold**: Set the price or % threshold
   - **Active**: Toggle on/off
4. Click **Create**

### Managing Alerts

View all your alerts in a table showing:

- Product
- Competitor
- Alert type
- Threshold
- Status (active/inactive)
- Last triggered

**To edit an alert:**
1. Click on the alert
2. Modify settings
3. Click **Save**

**To delete an alert:**
1. Click on the alert
2. Click **Delete**
3. Confirm

### Alert Notifications

When an alert triggers, you'll receive:

- **Email notification**: Sent immediately
- **In-app notification badge**: Red badge on the bell icon
- (Future) Slack or Teams notification

**To view notifications:**

1. Click the bell icon in the top navigation
2. See all recent alert triggers
3. Click to view details
4. Mark as read

### Alert Best Practices

- Set up alerts for your top 20% products (80/20 rule)
- Use "Competitor Beats Our Price" for price-sensitive categories
- Use "Out of Stock" to identify competitive opportunities
- Review and prune unused alerts monthly

---

## Importing Price Data

The platform supports importing competitor price data from external sources.

### Supported Import Methods

- **CSV Upload**: Manual upload of price data
- **API Import**: Automated API-based imports (contact admin to set up)

### CSV Format

The CSV must have these columns:

```
product_sku, competitor_name, competitor_price, competitor_url, competitor_sku, in_stock, scraped_at
```

**Example:**

```
SKU001, Takealot, 89.99, https://takealot.com/..., TAK123, true, 2024-12-14 10:30:00
SKU002, Wellness Warehouse, 350.00, https://well.co.za/..., WW456, true, 2024-12-14 10:35:00
```

### Uploading a CSV

1. Go to **Settings** → **Import Prices** (or dedicated import page)
2. Click **Upload CSV**
3. Select your file
4. Review the preview
5. Click **Import**
6. View import results:
   - Rows imported successfully
   - Rows failed (with error messages)

### Import Errors

Common errors:

- **Product not found**: SKU doesn't exist in PIM → Add product first
- **Invalid price**: Price is not a valid number → Fix CSV
- **Missing required field**: Required column is blank → Fill in data
- **Invalid date**: scraped_at is not in correct format → Use `YYYY-MM-DD HH:MM:SS`

### Automated Imports

For regular imports, contact your system administrator to:

- Set up API integration with price scraping service
- Schedule automated nightly imports
- Configure error notifications

---

## Exporting Data

All pricing data can be exported for further analysis.

### Export Formats

- **CSV**: For Excel or Google Sheets
- **Excel**: Formatted .xlsx file with multiple sheets

### How to Export

1. Navigate to any pricing page (Competitor Prices, Price History, etc.)
2. Apply desired filters
3. Click **Export** button
4. Choose format (CSV or Excel)
5. File downloads immediately

### What's Exported

Depends on the page:

- **Competitor Prices**: Current prices for all products
- **Price History**: Historical price data for selected product
- **Price Comparison Matrix**: Full matrix with all competitors
- **Margin Analysis**: Cost, price, margin data
- **Price Alerts**: All your configured alerts

---

## Tips and Best Practices

### Daily Routine

- Check **Dashboard** for new price changes
- Review **Active Alerts** notification badge
- Respond to critical alerts (competitor beats our price)

### Weekly Routine

- Review **Competitor Prices** page for trends
- Check **Price History** for top products
- Identify repricing opportunities using **Price Comparison Matrix**
- Export data for weekly pricing review meeting

### Monthly Routine

- Run **Margin Analysis** to optimize pricing strategy
- Review and prune inactive **Price Alerts**
- Analyze **Price History** for seasonal trends
- Update cost data in PIM for accurate margin calculations

### Competitive Pricing Strategy

**Match Pricing Strategy:**
- Set alerts for "Competitor Beats Our Price"
- Automatically match or beat by 5%
- Focus on price-sensitive categories

**Premium Pricing Strategy:**
- Monitor margin, not competitor price
- Justify premium with quality, service, brand
- Use alerts only for major competitor discounts

**Value Pricing Strategy:**
- Price based on cost + target margin
- Monitor competitor prices for context, not for matching
- Focus on margin analysis

### Working with Product Teams

- Share **Price Comparison Matrix** weekly
- Highlight repricing opportunities
- Provide **Price History** data for new product launches
- Use **Margin Analysis** to justify price changes

### Data Quality

- Import fresh competitor data at least weekly
- Verify competitor URLs are correct
- Remove discontinued products from tracking
- Validate that scraped prices match actual website prices

---

## Need Help?

- **Troubleshooting**: See [Troubleshooting Guide](troubleshooting-guide.md)
- **FAQ**: See [Frequently Asked Questions](faq.md)
- **Setting Up API Imports**: Contact your system administrator
- **Support**: Contact support@silvertreebrands.com

---

## Data Freshness

Competitor price data is:

- **Manually Imported**: As frequently as you upload CSVs
- **API Imported**: Typically nightly (check with admin)
- **Data Age**: Shown in "Last Updated" column

For the most accurate pricing decisions, ensure data is refreshed at least weekly.

---

**Document Version**: 1.0
**Last Updated**: 2025-12-14
**Platform Version**: Laravel 12 + Filament 4
