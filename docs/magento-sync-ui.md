# Magento Sync UI Implementation

## Overview

This document describes the Filament UI components added for Magento sync management.

## Components Implemented

### 1. Database Tracking

**Tables:**
- `sync_runs` - Tracks each sync execution with stats and status
- `sync_results` - Tracks individual item results within a sync

**Models:**
- `App\Models\SyncRun` - Sync run with relationships to results, user, entity type
- `App\Models\SyncResult` - Individual sync result (product, attribute option)

**Retention:**  
Sync results are automatically cleaned up after 30 days via:
```bash
php artisan sync:cleanup  # Default 30 days
php artisan sync:cleanup --days=60  # Custom retention
```

### 2. Attribute Edit Page Actions

**Location:** Attribute Edit Page (when `is_synced = true`)

**Actions:**
1. **Test Magento Mapping** - Verifies attribute exists in Magento and shows option count
2. **Sync Options from Magento** - Syncs select/multiselect options (visible only for those types)

**How it works:**
- Uses Magento as source of truth
- Replaces SPIM options with Magento options
- Queues job for async execution
- Shows notification with status

### 3. Entity Edit Page Actions

**Location:** All Entity Edit Pages (Products, Categories, etc.)

**Action:**
- **Sync to Magento** - Syncs the current product to Magento immediately

**How it works:**
- Dispatches `SyncSingleProduct` job
- Queues for async execution
- Shows notification to check Magento Sync page for results

### 4. Magento Sync Page

**Location:** Settings → Magento Sync

**Features:**

#### Stats Widget (Top of Page)
Shows 4 key metrics:
- **Last Sync** - When last sync ran and its status
- **Products Pending Sync** - Count of products with `value_approved != value_live`
- **Today's Syncs** - Number of syncs today with success/error counts
- **Active Sync** - Currently running sync or "Idle"

#### Sync Actions (Header)
- **Sync Options** - Sync attribute options for selected entity type
- **Sync Products** - Sync all products for selected entity type
- **Sync by SKU** - Sync specific products by entering SKUs (one per line)
- **Refresh** - Refresh the page

#### Sync History Table
Columns:
- Started - When sync began
- Type - options/products/full
- Entity Type - Which entity type
- Status - completed/partial/failed/running
- Total/Success/Errors - Item counts
- Duration - How long it took
- Triggered By - User or schedule

Row Actions:
- **View Errors** - Modal showing detailed error messages (visible when errors > 0)
- **View Details** - Modal showing full sync stats and summary

### 5. Queue Jobs

All syncs run asynchronously via Laravel Queue:

- `App\Jobs\Sync\SyncAttributeOptions` - Sync options for entity type
- `App\Jobs\Sync\SyncAllProducts` - Sync all products for entity type
- `App\Jobs\Sync\SyncSingleProduct` - Sync single product by entity

**Job tracking:**
- Creates `SyncRun` record when dispatched
- Updates stats as it progresses
- Logs individual results to `SyncResult`
- Marks complete with final status

### 6. Views

**Blade Components:**
- `resources/views/filament/pages/magento-sync.blade.php` - Main page
- `resources/views/filament/components/sync-errors.blade.php` - Error list modal
- `resources/views/filament/components/sync-details.blade.php` - Details modal
- `resources/views/filament/components/no-errors.blade.php` - Success state

## Usage Workflows

### Workflow 1: Sync Attribute Options

1. Go to Settings → Attributes
2. Edit an attribute marked `is_synced = true`
3. Click "Test Magento Mapping" to verify (optional)
4. Click "Sync Options from Magento" for select/multiselect
5. Confirm the action
6. Check Settings → Magento Sync for results

### Workflow 2: Sync Single Product

1. Go to Entities → [Product Type]
2. Edit a product
3. Click "Sync to Magento" in header
4. Confirm the action
5. Check Settings → Magento Sync for results

### Workflow 3: Sync All Products

1. Go to Settings → Magento Sync
2. Click "Sync Products" in header
3. Select entity type
4. Confirm
5. Watch progress in the sync history table

### Workflow 4: Sync Specific Products by SKU

1. Go to Settings → Magento Sync
2. Click "Sync by SKU" in header
3. Select entity type
4. Enter SKUs (one per line)
5. Confirm
6. Multiple jobs queued, one per product

### Workflow 5: Review Sync Errors

1. Go to Settings → Magento Sync
2. Find sync run with errors > 0
3. Click "Errors" button in that row
4. Modal shows detailed error messages
5. Each error shows item identifier and error message

### Workflow 6: Monitor Active Syncs

1. Go to Settings → Magento Sync
2. Check "Active Sync" stat widget
3. Shows "In Progress" if sync running
4. Shows entity type and sync type
5. Refresh page to see updates

## Technical Details

### Sync Run Lifecycle

1. **Created** - Job dispatched, `SyncRun` created with `status = 'running'`
2. **Processing** - Service logs results to `SyncResult` as it processes
3. **Stats Updated** - Counts incremented for success/error/skipped
4. **Completed** - Status set to `completed`, `partial`, or `failed`
5. **Error Summary** - If failed, error message stored in `error_summary`

### Result Logging

Each item processed creates a `SyncResult`:
```php
SyncResult::create([
    'sync_run_id' => $syncRun->id,
    'entity_id' => $entity->id,           // For products
    'attribute_id' => $attribute->id,     // For options
    'item_identifier' => 'SKU123',        // Display name
    'operation' => 'create|update|skip',
    'status' => 'success|error|warning',
    'error_message' => 'Error details',
    'details' => ['key' => 'value'],      // JSON metadata
]);
```

### User Attribution

All UI-triggered syncs track the user:
```php
SyncRun::create([
    'triggered_by' => 'user',
    'user_id' => auth()->id(),
    // ...
]);
```

Scheduled syncs use:
```php
'triggered_by' => 'schedule',
'user_id' => null,
```

### Permissions

All sync actions use Filament's built-in auth:
- User must be logged in
- No additional permissions required (editors can sync)
- Future: Can add policies if needed

## Monitoring & Maintenance

### Daily Checks

Check Magento Sync page for:
- Recent errors (red badges)
- Long-running syncs
- High error rates

### Cleanup

Run cleanup weekly/monthly:
```bash
# Via command
docker exec spim_app php artisan sync:cleanup

# Or schedule in Kernel.php
$schedule->command('sync:cleanup')->weekly();
```

### Troubleshooting

**Sync stuck in "running" status:**
- Job may have failed silently
- Check queue worker is running: `php artisan queue:work`
- Manually mark failed: Update `sync_runs` set `status = 'failed'`

**No results showing:**
- Ensure queue worker is running
- Check `sync_results` table for data
- Verify `SyncRun` has `sync_run_id` linkage

**High error rate:**
- Click "Errors" to see details
- Common causes: API connection, invalid SKUs, missing attributes
- Fix root cause and retry

## Future Enhancements

Potential additions:
1. Real-time updates (WebSockets/polling)
2. Retry failed items button
3. Cancel running sync button
4. Export error report (CSV/Excel)
5. Sync scheduling UI
6. Conflict resolution UI
7. Bulk approve pending products UI

## Files Modified/Created

**Models:**
- `app/Models/SyncRun.php`
- `app/Models/SyncResult.php`

**Jobs:**
- `app/Jobs/Sync/SyncAttributeOptions.php`
- `app/Jobs/Sync/SyncAllProducts.php`
- `app/Jobs/Sync/SyncSingleProduct.php`

**Filament Pages:**
- `app/Filament/Pages/MagentoSync.php`
- `app/Filament/Widgets/MagentoSyncStats.php`

**Filament Actions:**
- `app/Filament/Resources/AttributeResource/Pages/EditAttribute.php`
- `app/Filament/Resources/Pages/AbstractEditEntityRecord.php`

**Views:**
- `resources/views/filament/pages/magento-sync.blade.php`
- `resources/views/filament/components/sync-errors.blade.php`
- `resources/views/filament/components/sync-details.blade.php`
- `resources/views/filament/components/no-errors.blade.php`

**Commands:**
- `app/Console/Commands/CleanupOldSyncResults.php`

**Migrations:**
- `database/migrations/2025_10_09_103304_create_sync_tracking_tables.php`

**Services (Updated):**
- `app/Services/Sync/AttributeOptionSync.php` - Added database logging
- `app/Services/Sync/ProductSync.php` - Added database logging

