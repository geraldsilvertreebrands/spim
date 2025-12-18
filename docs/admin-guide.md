# Admin Guide

**For**: System Administrators
**Platform**: Silvertree Multi-Panel Platform
**Last Updated**: 2025-12-14

---

## Table of Contents

1. [Administrator Overview](#administrator-overview)
2. [User Management](#user-management)
3. [Role and Permission System](#role-and-permission-system)
4. [Brand Access Control](#brand-access-control)
5. [Panel Switching](#panel-switching)
6. [System Configuration](#system-configuration)
7. [Monitoring and Maintenance](#monitoring-and-maintenance)
8. [BigQuery Integration](#bigquery-integration)
9. [Security Best Practices](#security-best-practices)
10. [Troubleshooting Common Issues](#troubleshooting-common-issues)

---

## Administrator Overview

As an administrator, you have full access to all three panels and user management capabilities.

### Admin Privileges

- Access to **PIM Panel** (`/pim`)
- Access to **Supply Panel** (`/supply`)
- Access to **Pricing Panel** (`/pricing`)
- User management (create, edit, delete, activate/deactivate)
- Role assignment
- Brand access control
- System configuration

### Default Admin Account

Default admin credentials (change immediately after first login):

- **Email**: admin@silvertreebrands.com
- **Password**: password
- **Role**: admin

**IMPORTANT**: Change this password on first login!

---

## User Management

### Viewing Users

1. Navigate to **PIM Panel** → **Users**
2. See all users with:
   - Name
   - Email
   - Roles
   - Status (active/inactive)
   - Last login
   - Created date

### Creating a User

1. Go to **PIM Panel** → **Users** → **New User**
2. Fill in required fields:
   - **Name**: Full name
   - **Email**: Must be unique
   - **Password**: Minimum 8 characters
   - **Roles**: Select one or more roles (see [Role System](#role-and-permission-system))
   - **Is Active**: Enable/disable account
3. Click **Create**
4. User receives welcome email (if email is configured)

### Editing a User

1. Click on a user in the Users table
2. Modify fields as needed:
   - Name
   - Email (must remain unique)
   - Roles
   - Active status
3. Click **Save**

**Note**: You cannot edit a user's password here. Users must reset their password via "Forgot Password" or you can force a password reset.

### Deactivating a User

Instead of deleting users (which removes audit trail), deactivate them:

1. Edit the user
2. Toggle **Is Active** to OFF
3. Click **Save**

Deactivated users:
- Cannot log in
- Do not appear in user lists (except admin views)
- Maintain history and audit trail

### Deleting a User

Only delete users if absolutely necessary (e.g., spam account, duplicate):

1. Edit the user
2. Click **Delete** button
3. Confirm deletion

**Warning**: Deleting a user removes their audit trail and cannot be undone!

---

## Role and Permission System

The platform uses a role-based access control (RBAC) system.

### Available Roles

| Role | Panel Access | Description |
|------|--------------|-------------|
| `admin` | All panels | Full system access |
| `pim-editor` | PIM only | Product data management |
| `supplier-basic` | Supply only | Basic supplier insights |
| `supplier-premium` | Supply only | Full supplier insights |
| `pricing-analyst` | Pricing only | Pricing management |

### Assigning Roles

When creating or editing a user:

1. Go to **Roles** field
2. Select one or more roles from dropdown
3. Save

**Multi-Role Users:**
- A user can have multiple roles (e.g., `admin` + `pim-editor`)
- Panel access is cumulative (union of all roles)

### Panel Access Rules

**PIM Panel** (`/pim`):
- Requires `admin` OR `pim-editor` role

**Supply Panel** (`/supply`):
- Requires `admin` OR `supplier-basic` OR `supplier-premium` role

**Pricing Panel** (`/pricing`):
- Requires `admin` OR `pricing-analyst` role

**Homepage** (`/`):
- Redirects to user's default panel based on their roles

### Permission Levels

Within each panel, permissions include:

- **View**: Can see data
- **Create**: Can create new records
- **Edit**: Can modify existing records
- **Delete**: Can delete records
- **Export**: Can export data

Permissions are automatically granted based on role.

### Creating Custom Roles (Advanced)

To create a new custom role:

1. Edit `database/seeders/RoleSeeder.php`
2. Add new role definition
3. Define permissions
4. Run: `php artisan db:seed --class=RoleSeeder`

See [Role Permission Reference](role-permission-reference.md) for details.

---

## Brand Access Control

Control which brands each user can access.

### Brand Model

Brands are synced from BigQuery and represent product brands (e.g., "Faithful to Nature", "Pet Heaven", etc.).

### Assigning Brand Access

**Via Database:**

1. Access the `supplier_brand_access` table
2. Create a record linking:
   - `user_id`: The user's ID
   - `brand_id`: The brand's ID
3. Save

**Via Seeder (Recommended for Initial Setup):**

Edit `database/seeders/TestUserSeeder.php` to add brand access mappings.

### Brand Scoping

When a user has brand access assigned:

- **Supply Panel**: Only shows data for their brands
- **Pricing Panel**: Only shows pricing for their brands
- **PIM Panel**: (Optional) Can filter by brand

**Admin users** bypass brand scoping and see all brands.

### Brand Selector

Users with access to multiple brands see a **Brand Selector** dropdown in:

- Supply Dashboard
- Pricing pages

Selecting a brand filters all data to that brand.

### Competitor Brands

Brands can have competitors defined in the `brand_competitors` table:

- Used for competitive benchmarking in Supply panel
- Used for price comparisons in Pricing panel

To add competitors:

1. Edit the `brand_competitors` table
2. Link `brand_id` to `competitor_brand_id`

---

## Panel Switching

Admins can switch between panels using the top navigation.

### Panel Navigation Menu

In the top navigation bar, you'll see:

- **PIM** (if you have pim access)
- **Supply** (if you have supply access)
- **Pricing** (if you have pricing access)

Click any panel name to switch.

### Panel Redirection

- `/` → Redirects to your default panel
- `/admin` → Redirects to `/pim` (legacy)
- `/pim` → PIM Panel
- `/supply` → Supply Panel
- `/pricing` → Pricing Panel

### Default Panel

The default panel for a user is determined by:

1. If they have `admin` role → PIM
2. If they have `pim-editor` → PIM
3. If they have `supplier-basic` or `supplier-premium` → Supply
4. If they have `pricing-analyst` → Pricing

---

## System Configuration

### Environment Variables

Key configuration in `.env`:

```env
# Application
APP_NAME="Silvertree Platform"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://silvertree.example.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=spim
DB_USERNAME=spim_user
DB_PASSWORD=secure_password

# Company ID (3=FtN, 5=Pet Heaven, 9=UCOOK)
COMPANY_ID=3

# BigQuery
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json
BIGQUERY_PROJECT_ID=silvertree-poc
BIGQUERY_DATASET=sh_output

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@silvertreebrands.com"
MAIL_FROM_NAME="${APP_NAME}"

# OpenAI (for AI pipelines)
OPENAI_API_KEY=sk-...

# Magento Sync
MAGENTO_BASE_URL=https://magento.example.com
MAGENTO_ACCESS_TOKEN=your_token
```

### Updating Configuration

1. SSH into server
2. Edit `.env` file
3. Run: `php artisan config:clear`
4. Run: `php artisan cache:clear`
5. Restart PHP-FPM or reload web server

### Cache Management

Clear caches when config changes:

```bash
php artisan cache:clear        # Application cache
php artisan config:clear       # Config cache
php artisan route:clear        # Route cache
php artisan view:clear         # View cache
```

---

## Monitoring and Maintenance

### Daily Tasks

- **Check Error Logs**: Review `storage/logs/laravel.log` for errors
- **Monitor BigQuery Usage**: Check query costs in Google Cloud Console
- **Review Failed Jobs**: Check `failed_jobs` table for stuck jobs

### Weekly Tasks

- **Review User Activity**: Check login logs for suspicious activity
- **Review Price Alert Triggers**: Ensure alerts are working correctly
- **Check Database Size**: Monitor disk usage

### Monthly Tasks

- **Review User Accounts**: Deactivate inactive users
- **Review Brand Access**: Ensure users have correct brand assignments
- **Review Pipeline Performance**: Check AI pipeline costs and quality
- **Database Backup Verification**: Test restore from backup

### Logs

Important log files:

- **Laravel Log**: `storage/logs/laravel.log`
- **Web Server Log**: `/var/log/nginx/access.log` (or Apache equivalent)
- **PHP Error Log**: `/var/log/php-fpm/error.log`
- **Queue Log**: `storage/logs/queue.log` (if configured)

View logs:

```bash
tail -f storage/logs/laravel.log
```

### Database Backups

**Automated Backups** (configure in cron):

```bash
# Daily backup at 2 AM
0 2 * * * /usr/bin/mysqldump -u spim_user -p'password' spim > /backups/spim_$(date +\%Y\%m\%d).sql
```

**Manual Backup**:

```bash
mysqldump -u spim_user -p spim > backup_$(date +%Y%m%d).sql
```

**Restore from Backup**:

```bash
mysql -u spim_user -p spim < backup_20241214.sql
```

### Monitoring Tools

Consider setting up:

- **Sentry**: Error tracking and alerting
- **New Relic**: Performance monitoring
- **Uptime Robot**: Uptime monitoring
- **Google Cloud Monitoring**: BigQuery usage and performance

---

## BigQuery Integration

### Setup

1. **Google Cloud Service Account**:
   - Create service account in GCP Console
   - Grant BigQuery Data Viewer role
   - Download JSON key file
   - Place in `/path/to/credentials/service-account.json`
   - Set `GOOGLE_APPLICATION_CREDENTIALS` in `.env`

2. **Environment Variables**:
   ```env
   GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json
   BIGQUERY_PROJECT_ID=silvertree-poc
   BIGQUERY_DATASET=sh_output
   COMPANY_ID=3
   ```

3. **Test Connection**:
   ```bash
   php artisan tinker
   >>> app(\App\Services\BigQueryService::class)->testConnection()
   ```

### BigQuery Tables

The platform queries these BigQuery tables:

- `dim_product`: Product master data
- `dim_customer`: Customer master data
- `dim_brand`: Brand data
- `fact_order_item`: Sales transaction data
- `fact_stock_level`: Inventory levels

### BigQuery Permissions

**CRITICAL**: BigQuery access is **READ-ONLY**.

The service account MUST have:
- `bigquery.dataViewer` role
- `bigquery.jobUser` role (to run queries)

The service account MUST NOT have:
- `bigquery.dataEditor` (prevents writes)
- `bigquery.dataOwner` (prevents schema changes)

### Cost Management

BigQuery charges by data scanned. To minimize costs:

- **Partition Filtering**: Always filter by date partitions
- **Column Selection**: Select only needed columns, not `SELECT *`
- **Caching**: Results are cached for 24 hours
- **Query Limits**: Use `LIMIT` clauses

Monitor costs in: [Google Cloud Console → BigQuery → Query History](https://console.cloud.google.com/bigquery)

### Syncing Brands from BigQuery

Brands are synced from BigQuery to local database:

**Manual Sync**:
```bash
php artisan brands:sync
```

**Automated Sync** (add to cron):
```bash
0 3 * * * cd /path/to/app && php artisan brands:sync >> /dev/null 2>&1
```

This syncs brands for the `COMPANY_ID` specified in `.env`.

---

## Security Best Practices

### Strong Passwords

Enforce strong passwords:

1. Minimum 8 characters
2. Mix of upper/lower case
3. Include numbers and symbols
4. No common passwords (e.g., "password123")

### Two-Factor Authentication (Future)

2FA is planned but not yet implemented. Consider adding via package like `laravel/fortify`.

### SSL/HTTPS

**Production MUST use HTTPS**.

1. Obtain SSL certificate (Let's Encrypt recommended)
2. Configure web server (Nginx/Apache) for HTTPS
3. Force HTTPS redirect in `.env`:
   ```env
   APP_URL=https://silvertree.example.com
   ```
4. Set in `app/Http/Middleware/TrustProxies.php`:
   ```php
   protected $headers = Request::HEADER_X_FORWARDED_ALL;
   ```

### Rate Limiting

API endpoints are rate-limited:

- Supply API: 60 requests per minute per user
- Authentication: 5 login attempts per minute per IP

Adjust in `routes/api.php` and `app/Http/Kernel.php`.

### Session Security

Sessions are encrypted and signed. Configure in `config/session.php`:

```php
'secure' => env('SESSION_SECURE_COOKIE', true), // HTTPS only
'http_only' => true, // Prevent JavaScript access
'same_site' => 'lax', // CSRF protection
```

### CSRF Protection

All forms include CSRF tokens automatically. Ensure all POST/PUT/DELETE requests include:

```blade
@csrf
```

### SQL Injection Prevention

Use Eloquent ORM or parameterized queries:

**Good**:
```php
User::where('email', $email)->first();
DB::table('users')->where('email', $email)->get();
```

**Bad**:
```php
DB::select("SELECT * FROM users WHERE email = '$email'"); // NEVER DO THIS
```

### XSS Prevention

Blade templates escape output by default:

```blade
{{ $user->name }} <!-- Escaped, safe -->
{!! $html !!}     <!-- NOT escaped, dangerous - use sparingly -->
```

### File Upload Security

If allowing file uploads:

1. Validate file type
2. Validate file size
3. Store outside public directory
4. Rename files (don't trust user-provided names)
5. Scan for malware (if possible)

---

## Troubleshooting Common Issues

### "Access Denied" When Accessing Panel

**Symptom**: User gets "Access Denied" or redirected to login

**Cause**: User doesn't have required role

**Solution**:
1. Check user's roles in PIM → Users
2. Assign appropriate role:
   - PIM Panel → `admin` or `pim-editor`
   - Supply Panel → `admin`, `supplier-basic`, or `supplier-premium`
   - Pricing Panel → `admin` or `pricing-analyst`

### BigQuery "Permission Denied" Error

**Symptom**: BigQuery queries fail with permission error

**Cause**: Service account lacks permissions

**Solution**:
1. Check service account has `bigquery.dataViewer` role
2. Verify `GOOGLE_APPLICATION_CREDENTIALS` path is correct
3. Test: `php artisan tinker` → `app(\App\Services\BigQueryService::class)->testConnection()`

### "No Brand Access" or Empty Data

**Symptom**: Supplier sees empty dashboard

**Cause**: User has no brands assigned

**Solution**:
1. Check `supplier_brand_access` table
2. Ensure user has at least one brand assigned
3. If admin, they should see all brands regardless

### Magento Sync Fails

**Symptom**: Sync runs but shows errors

**Cause**: Various - check sync results

**Solution**:
1. Go to PIM → Magento Sync
2. Click on the failed sync run
3. Review error messages
4. Common issues:
   - **Authentication failed**: Check `MAGENTO_ACCESS_TOKEN`
   - **Product not found**: Product may have been deleted in Magento
   - **Attribute mismatch**: Attribute type doesn't match between systems

### Price Alerts Not Triggering

**Symptom**: Price changes but no alert received

**Cause**: Alert may be inactive or misconfigured

**Solution**:
1. Go to Pricing → Price Alerts
2. Check alert is **Active**
3. Check threshold is correct
4. Verify email configuration in `.env` is working
5. Check `notifications` table for sent notifications

### Dashboard KPIs Showing "No Data"

**Symptom**: Supply or Pricing dashboard shows "No Data"

**Cause**: BigQuery has no data for selected filters

**Solution**:
1. Change date range (try "Last 90 Days")
2. Change brand selector to "All Brands"
3. Check BigQuery data exists: Run query in BigQuery console
4. Verify `COMPANY_ID` in `.env` matches data in BigQuery

### Pipeline Fails with "OpenAI API Error"

**Symptom**: AI pipeline runs but fails

**Cause**: OpenAI API issue

**Solution**:
1. Check `OPENAI_API_KEY` is valid
2. Check API quota: https://platform.openai.com/usage
3. Check pipeline prompt is valid
4. Review error in pipeline run details

### High BigQuery Costs

**Symptom**: BigQuery usage costs are high

**Cause**: Inefficient queries or high traffic

**Solution**:
1. Review query costs in Google Cloud Console
2. Optimize queries to select fewer columns
3. Ensure date partition filters are used
4. Enable result caching
5. Consider materialized views or scheduled queries
6. Limit dashboard refreshes

---

## Need Help?

For issues not covered here:

- **Developer Documentation**: See technical docs in `docs/`
- **Role Reference**: See [Role Permission Reference](role-permission-reference.md)
- **Architecture**: See [Multi-Panel Architecture Overview](multi-panel-architecture-overview.md)
- **Security**: See [Security Audit](security-audit.md)
- **Support**: Contact support@silvertreebrands.com or file an issue in the project repository

---

**Document Version**: 1.0
**Last Updated**: 2025-12-14
**Platform Version**: Laravel 12 + Filament 4
