# Magento Sync Implementation Summary

## Overview

The Magento sync system provides bi-directional synchronization between SPIM and Magento 2, handling both attribute options and product data.

## Architecture

### Class Structure

```
app/Services/
├── MagentoApiClient.php          # REST API client
└── Sync/
    ├── AbstractSync.php           # Base class for all syncs
    ├── AttributeOptionSync.php    # Option sync logic
    └── ProductSync.php            # Product sync logic

app/Console/Commands/
├── SyncMagentoOptions.php        # CLI for option sync
└── SyncMagento.php               # CLI for product sync
```

### Key Design Decisions

1. **No External Packages**: Uses Laravel's built-in `Http` facade (Guzzle wrapper) instead of external Magento client libraries
   - Better testing support with `Http::fake()`
   - Automatic retry logic and error handling
   - No version compatibility issues

2. **Per-Product Transactions**: Each product syncs in its own transaction
   - Prevents all-or-nothing failures
   - Better for large catalogs
   - Easier to identify and retry failed products

3. **Separate Option Sync**: Attribute options are synced separately from products
   - Must run before product sync to avoid errors
   - Can be run on-demand or scheduled
   - Detects and reports conflicts without corrupting data

4. **Override Support**: `value_override` takes precedence over `value_approved`
   - Allows human intervention to override AI-generated values
   - Syncs properly to Magento

5. **Comprehensive Logging**: Dedicated log channel with daily rotation
   - All API calls logged
   - Errors with full context
   - Stats tracked for each run

## Sync Flow

### Option Sync Flow

```
1. Load all synced select/multiselect attributes for entity type
2. For each attribute:
   a. Fetch options from both SPIM and Magento
   b. Detect conflicts (same label different ID, or vice versa)
   c. If conflicts found, abort and report
   d. Sync missing options in both directions
   e. Commit in single transaction per attribute
```

### Product Sync Flow

```
1. Validate all synced attributes (Step 0)
   - Check attribute types are compatible
   - Check no relationship attributes marked for sync
   
2. Pull from Magento → SPIM (Step 1)
   - Fetch products from Magento
   - For new products:
     * Create entity record
     * Write input attributes to eav_input
     * Write versioned attributes to eav_versioned (all 3 values equal)
   - For existing products:
     * Update only input attributes
     * Leave versioned attributes unchanged
   
3. Push from SPIM → Magento (Step 2)
   - Find SPIM products that need syncing
   - For new products in Magento:
     * Send all synced attributes
     * Set status=disabled unless status is synced
   - For existing products:
     * Find versioned attributes where value_approved != value_live
     * Send only changed attributes
     * Use value_override if present, else value_approved
   - Update value_live after successful push
```

## Data Flow Diagrams

### Initial Import (Magento → SPIM)

```
Magento Product
    ↓
MagentoApiClient.getProducts()
    ↓
ProductSync.importProduct()
    ↓
┌─────────────────────────────┐
│ For INPUT attributes:       │
│ → eav_input                 │
│   (value)                   │
└─────────────────────────────┘
┌─────────────────────────────┐
│ For VERSIONED attributes:   │
│ → eav_versioned             │
│   (all 3 values = imported) │
│   value_current = X         │
│   value_approved = X        │
│   value_live = X            │
└─────────────────────────────┘
```

### Export with Changes (SPIM → Magento)

```
SPIM Entity
    ↓
Find versioned attrs where:
  value_approved != value_live
    ↓
Get value to sync:
  value_override ?? value_approved
    ↓
ProductSync.exportProduct()
    ↓
MagentoApiClient.updateProduct()
    ↓
On success:
  value_live = (override ?? approved)
```

## Configuration

### Environment Variables

```bash
MAGENTO_BASE_URL=https://your-magento-site.com
MAGENTO_ACCESS_TOKEN=your_integration_token_here
```

### Magento Integration Setup

1. In Magento Admin: System → Integrations → Add New Integration
2. Set API permissions (Catalog, Products, etc.)
3. Activate and copy the Access Token
4. Add to SPIM `.env` file

## Usage Examples

### Initial Setup

```bash
# 1. Configure .env with Magento credentials
# 2. Ensure attributes are marked is_synced=true
# 3. Run option sync first
php artisan sync:magento:options product

# 4. Run full product sync
php artisan sync:magento product
```

### Ongoing Operations

```bash
# Sync a single product on-demand
php artisan sync:magento product --sku=ABC123

# Scheduled sync (in app/Console/Kernel.php)
$schedule->command('sync:magento:options product')->dailyAt('02:00');
$schedule->command('sync:magento product')->cron('0 */4 * * *');
```

## Error Handling

### Common Errors

1. **Option Conflicts**
   ```
   Same label 'Red' has different IDs: SPIM=123, Magento=456
   ```
   **Resolution**: Manually reconcile in database or Magento admin

2. **Missing Attributes**
   ```
   Attribute 'custom_field' not found in Magento
   ```
   **Resolution**: Create attribute in Magento or unmark as synced

3. **API Authentication Errors**
   ```
   Failed to fetch products: 401 Unauthorized
   ```
   **Resolution**: Check MAGENTO_ACCESS_TOKEN is valid

### Retry Logic

The MagentoApiClient automatically retries:
- Connection errors (3 attempts)
- 5xx server errors (3 attempts)
- Exponential backoff: 100ms, 200ms, 400ms

## Testing Strategy

### Unit Tests (TODO)

- Attribute type mapping
- Option conflict detection
- Value conversion logic

### Feature Tests (TODO)

- Mock Magento API with `Http::fake()`
- Assert correct database writes
- Assert correct API calls made
- Test error handling paths

## Performance Considerations

### Current Implementation

- Processes one product at a time
- One API call per product
- One transaction per product

### Future Optimizations

1. **Batch API Calls**: Use Magento bulk API endpoints
2. **Parallel Processing**: Queue jobs for multiple products
3. **Change Detection**: Only fetch changed products from Magento
4. **Incremental Sync**: Track last sync timestamp

## Monitoring

### Logs

All sync operations log to `storage/logs/magento-sync.log`:

```
[2025-10-02 10:00:00] INFO: Starting product sync for product
[2025-10-02 10:00:01] INFO: Validated 15 synced attributes
[2025-10-02 10:00:02] INFO: Pulling products from Magento
[2025-10-02 10:00:05] INFO: Created new entity for SKU: ABC123
[2025-10-02 10:00:10] INFO: Completed product sync {"created":5,"updated":12,"errors":0,"skipped":3}
```

### Metrics to Track

- Sync duration
- Products created/updated/failed
- API response times
- Error rates by type

## Security

### API Token

- Store in `.env`, never commit
- Use Magento integration tokens (not admin passwords)
- Rotate tokens periodically
- Limit token permissions to required scopes

### Data Validation

- All attribute values validated before sync
- Type checking on API responses
- SQL injection protected by query builder

## Maintenance

### Regular Tasks

1. Monitor logs for errors
2. Check sync statistics
3. Verify data consistency between systems
4. Update option mappings as needed

### Troubleshooting

```bash
# Check logs
tail -f storage/logs/magento-sync.log

# Test API connection
php artisan tinker
> app(\App\Services\MagentoApiClient::class)->getProducts();

# Check attribute configuration
php artisan tinker
> \App\Models\Attribute::where('is_synced', true)->get(['name', 'data_type', 'attribute_type']);
```

## Future Enhancements

See `docs/phase5.md` for detailed list of planned enhancements.

Priority items:
1. Image upload support (download URL → upload to Magento media API)
2. Comprehensive test suite
3. Progress indicators for long-running syncs
4. Dry-run mode for testing



