# Troubleshooting Guide

**Platform**: Silvertree Multi-Panel Platform
**Last Updated**: 2025-12-14

---

## Table of Contents

1. [Login and Authentication Issues](#login-and-authentication-issues)
2. [Panel Access Issues](#panel-access-issues)
3. [Dashboard and Data Issues](#dashboard-and-data-issues)
4. [BigQuery Errors](#bigquery-errors)
5. [Product and PIM Issues](#product-and-pim-issues)
6. [Pricing Panel Issues](#pricing-panel-issues)
7. [Performance Issues](#performance-issues)
8. [Error Messages](#error-messages)
9. [Getting Help](#getting-help)

---

## Login and Authentication Issues

### Cannot Login - "Invalid Credentials"

**Problem**: Email and password don't work.

**Solutions**:

1. **Verify email**: Ensure email is spelled correctly (case-insensitive)
2. **Reset password**:
   - Click "Forgot Password" on login page
   - Enter your email
   - Check email for reset link
   - Follow link to set new password
3. **Check account status**: Contact admin to verify your account is active

### Forgot Password Link Doesn't Work

**Problem**: Password reset email not received.

**Solutions**:

1. **Check spam folder**: Password reset emails may be filtered
2. **Wait 5 minutes**: Email may be delayed
3. **Verify email configuration**: Contact admin to check email is configured
4. **Contact admin**: Request manual password reset

### "Account Inactive" Error

**Problem**: Account has been deactivated.

**Solutions**:

1. **Contact admin**: Request account reactivation
2. **Provide business justification**: Explain why you need access

### Session Timeout - Logged Out Automatically

**Problem**: You're logged out after inactivity.

**Solutions**:

1. **Expected behavior**: Sessions expire after 2 hours of inactivity for security
2. **Log back in**: Simply log in again
3. **Save work frequently**: Don't leave unsaved changes

---

## Panel Access Issues

### "Access Denied" When Visiting Panel

**Problem**: You get "Access Denied" or redirected when visiting `/pim`, `/supply`, or `/pricing`.

**Solutions**:

**For PIM Panel** (`/pim`):
- Requires `admin` or `pim-editor` role
- Contact admin to assign role

**For Supply Panel** (`/supply`):
- Requires `admin`, `supplier-basic`, or `supplier-premium` role
- Contact admin to assign role

**For Pricing Panel** (`/pricing`):
- Requires `admin` or `pricing-analyst` role
- Contact admin to assign role

### Can't Switch Between Panels

**Problem**: Panel navigation links not visible.

**Solutions**:

1. **Check roles**: You can only access panels for which you have roles
2. **Admin users**: Should see all three panels in navigation
3. **Refresh page**: Try hard refresh (Ctrl+Shift+R or Cmd+Shift+R)

### Redirected to Wrong Panel

**Problem**: Homepage redirects to unexpected panel.

**Solutions**:

1. **Expected behavior**: Redirect is based on your primary role
2. **Bookmark specific panel**: Use direct URLs:
   - PIM: `/pim`
   - Supply: `/supply`
   - Pricing: `/pricing`

---

## Dashboard and Data Issues

### Dashboard Shows "No Data"

**Problem**: Dashboard KPIs and charts are empty.

**Solutions**:

**For Supply Panel**:

1. **Change date range**:
   - Try "Last 90 Days" or "Last Year"
   - Recent dates may not have data yet

2. **Check brand filter**:
   - If you see a brand dropdown, try "All Brands"
   - You may not have data for the selected brand

3. **Verify brand access**:
   - Contact admin to confirm you have brands assigned
   - Check `supplier_brand_access` table (admin only)

4. **Check BigQuery data**:
   - Admin can verify data exists in BigQuery
   - Data may not exist for your company/brand

**For Pricing Panel**:

1. **Import price data**:
   - Pricing data must be imported via CSV or API
   - Contact admin to import initial dataset

2. **Check product tracking**:
   - Products must be added to price tracking list
   - Contact admin to configure tracking

### KPIs Show Unexpected Values

**Problem**: Numbers look wrong or inconsistent.

**Solutions**:

1. **Verify date range**: Check the period selector
2. **Check brand filter**: Ensure correct brand is selected
3. **Verify vs vs previous period**: KPI may be showing % change
4. **Currency**: All values should be in ZAR
5. **Contact admin**: If values are clearly wrong, report to admin

### Charts Not Loading

**Problem**: Charts show loading spinner forever or error.

**Solutions**:

1. **Refresh page**: Try hard refresh (Ctrl+Shift+R)
2. **Check browser console**: Open developer tools (F12) and check for errors
3. **Disable browser extensions**: Ad blockers may interfere
4. **Try different browser**: Test in Chrome, Firefox, or Safari
5. **Clear cache**: Clear browser cache and cookies

### "Loading..." Never Completes

**Problem**: Page stuck on loading.

**Solutions**:

1. **Wait 30 seconds**: BigQuery queries can be slow
2. **Check network**: Verify internet connection
3. **Refresh page**: Hard refresh
4. **Check browser console**: Look for JavaScript errors
5. **Contact admin**: May be server-side issue

---

## BigQuery Errors

### "BigQuery Error: Access Denied"

**Problem**: You don't have permission to access BigQuery data.

**Solutions**:

**For Users**:
- This is a backend issue - contact admin

**For Admins**:
1. Check service account has `bigquery.dataViewer` role
2. Verify `GOOGLE_APPLICATION_CREDENTIALS` path in `.env`
3. Test connection: `php artisan tinker` → `app(\App\Services\BigQueryService::class)->testConnection()`

### "BigQuery Error: Table Not Found"

**Problem**: BigQuery table doesn't exist.

**Solutions**:

**For Admins**:
1. Verify table name in code matches BigQuery
2. Check `BIGQUERY_PROJECT_ID` and `BIGQUERY_DATASET` in `.env`
3. Verify table exists in BigQuery console
4. Check company_id filter - table may exist but have no data for this company

### "BigQuery Error: Query Failed"

**Problem**: BigQuery query execution failed.

**Solutions**:

1. **Temporary error**: Try again in 30 seconds
2. **Check BigQuery quotas**: Admin should check Google Cloud Console
3. **Contact admin**: Provide error message and timestamp

### Slow BigQuery Queries

**Problem**: Queries take > 10 seconds.

**Solutions**:

**For Admins**:
1. Reduce date range to scan less data
2. Add WHERE clauses to filter partitions
3. Select specific columns instead of `SELECT *`
4. Enable query result caching
5. Consider materialized views for frequently-accessed data

---

## Product and PIM Issues

### Product Not Saving

**Problem**: Click "Save" but product doesn't save.

**Solutions**:

1. **Check validation errors**:
   - Scroll to top of form
   - Red error messages indicate required fields or invalid data

2. **Required fields**:
   - SKU (must be unique)
   - Name

3. **Unique SKU**:
   - If SKU already exists, choose a different one
   - Search for existing product first

4. **Check browser console**:
   - Open developer tools (F12)
   - Look for errors

### Can't Delete Product

**Problem**: Delete button missing or doesn't work.

**Solutions**:

1. **Check permissions**: Only certain roles can delete
2. **Product may be in use**: Product may be referenced elsewhere (orders, etc.)
3. **Contact admin**: Request deletion

### Attribute Not Showing on Form

**Problem**: Created an attribute but it doesn't appear on product form.

**Solutions**:

1. **Assign to entity type**:
   - Edit the attribute
   - Check it's assigned to "Product" entity type

2. **Assign to attribute section**:
   - Attributes without sections may not display
   - Edit attribute and assign to a section

3. **Refresh page**: Try hard refresh

### Magento Sync Failing

**Problem**: Sync runs but shows errors.

**Solutions**:

1. **Check sync results**:
   - Go to PIM → Magento Sync
   - Click on failed sync run
   - Review individual error messages

2. **Common errors**:

   **"Authentication failed"**:
   - Verify `MAGENTO_ACCESS_TOKEN` in `.env`
   - Token may have expired - generate new one in Magento

   **"Product not found"**:
   - Product may have been deleted in Magento
   - Skip this product

   **"Attribute mismatch"**:
   - Attribute type differs between systems
   - Update attribute to match

3. **Contact admin**: Provide sync run ID and error details

---

## Pricing Panel Issues

### Price Data Not Showing

**Problem**: Pricing panel shows "No price data available".

**Solutions**:

1. **Import price data**:
   - Pricing relies on imported data
   - Contact admin to upload initial CSV

2. **Check product tracking**:
   - Only tracked products show price data
   - Verify products are in tracking list

### Price Alerts Not Working

**Problem**: Price changes but no alert received.

**Solutions**:

1. **Check alert is active**:
   - Go to Price Alerts
   - Verify alert toggle is ON

2. **Check threshold**:
   - Alert only triggers if threshold is met
   - Verify threshold is set correctly

3. **Check email**:
   - Verify email address in your profile
   - Check spam folder

4. **Email not configured**:
   - Contact admin to verify email is configured

### Can't Import CSV

**Problem**: CSV upload fails with errors.

**Solutions**:

1. **Check CSV format**:
   - Required columns: `product_sku`, `competitor_name`, `competitor_price`, `scraped_at`
   - Header row must match exactly

2. **Check data**:
   - SKUs must exist in PIM
   - Prices must be valid numbers
   - Dates must be in format `YYYY-MM-DD HH:MM:SS`

3. **Check file encoding**:
   - Save CSV as UTF-8
   - Avoid special characters

4. **Review import errors**:
   - After upload, check error report
   - Fix rows with errors and re-import

---

## Performance Issues

### Page Loading Slowly

**Problem**: Pages take > 5 seconds to load.

**Solutions**:

1. **Expected for BigQuery**: First load can be slow, subsequent loads are cached
2. **Reduce date range**: Shorter periods load faster
3. **Reduce filters**: Too many filters can slow queries
4. **Close unused tabs**: Each tab maintains database connection
5. **Check internet speed**: Slow connection affects performance
6. **Contact admin**: Persistent slowness may indicate server issue

### Browser Freezing

**Problem**: Browser becomes unresponsive.

**Solutions**:

1. **Large dataset**: Loading 1000+ products can freeze browser
2. **Use pagination**: Load data in pages, not all at once
3. **Close other tabs**: Free up browser memory
4. **Increase browser memory**: Close other applications
5. **Use different browser**: Try Chrome or Firefox

### Export Takes Too Long

**Problem**: CSV export never completes.

**Solutions**:

1. **Large dataset**: Exporting 10,000+ rows can take time
2. **Reduce filters**: Export only what you need
3. **Try smaller batches**: Export in multiple smaller files
4. **Wait longer**: May take 2-3 minutes for large exports
5. **Contact admin**: May need to increase server timeout

---

## Error Messages

### "419 - Page Expired"

**Problem**: CSRF token expired.

**Solutions**:

1. **Refresh page**: Reload the page
2. **Resubmit form**: Fill in form again and submit
3. **Cause**: Session expired or page was open too long

### "500 - Internal Server Error"

**Problem**: Server-side error occurred.

**Solutions**:

1. **Temporary error**: Refresh and try again
2. **Contact admin**: Provide:
   - What you were doing
   - Timestamp
   - Screenshot of error

### "403 - Forbidden"

**Problem**: You don't have permission for this action.

**Solutions**:

1. **Check role**: Contact admin to verify you have required permissions
2. **Brand access**: For Supply/Pricing, verify you have access to the brand

### "422 - Validation Error"

**Problem**: Form data is invalid.

**Solutions**:

1. **Read error message**: Scroll to top of form
2. **Fix highlighted fields**: Red fields indicate errors
3. **Required fields**: Fill in all required fields
4. **Format**: Ensure emails, URLs, numbers are in correct format

---

## Getting Help

### Before Contacting Support

Collect this information:

1. **What you were doing**: Step-by-step actions
2. **What happened**: Expected vs actual behavior
3. **Error messages**: Full text of any errors
4. **Screenshot**: If applicable
5. **Browser and OS**: Chrome on Windows, Safari on Mac, etc.
6. **Timestamp**: When the issue occurred
7. **Your role**: PIM editor, supplier, etc.

### Contact Channels

**For Users**:

1. **Check this guide first**: Most issues have solutions here
2. **Check FAQ**: See [FAQ](faq.md)
3. **Contact admin**: For account/access issues
4. **Email support**: support@silvertreebrands.com

**For Admins**:

1. **Check logs**: `storage/logs/laravel.log`
2. **Check admin guide**: See [Admin Guide](admin-guide.md)
3. **Check architecture docs**: See [Multi-Panel Architecture](multi-panel-architecture-overview.md)
4. **Email support**: support@silvertreebrands.com
5. **File bug report**: Include logs, steps to reproduce, environment details

### Response Times

- **Critical (system down)**: 1 hour
- **High (major feature broken)**: 4 hours
- **Medium (minor feature issue)**: 1 business day
- **Low (question, enhancement)**: 2 business days

---

## Common Solutions Quick Reference

| Problem | Quick Fix |
|---------|-----------|
| Can't login | Check email spelling, use "Forgot Password" |
| Access denied to panel | Contact admin for role assignment |
| Dashboard shows "No Data" | Change date range to "Last 90 Days", check brand filter |
| BigQuery error | Contact admin - backend issue |
| Product won't save | Check for red validation errors at top of form |
| Page loading slowly | Reduce date range, close unused tabs |
| Chart not loading | Hard refresh (Ctrl+Shift+R), check browser console |
| Price alert not working | Verify alert is active, check threshold |
| CSV import fails | Check CSV format, verify SKUs exist |
| 419 error | Refresh page and resubmit form |
| 500 error | Refresh and try again, contact admin if persists |

---

**Document Version**: 1.0
**Last Updated**: 2025-12-14
**Platform Version**: Laravel 12 + Filament 4
