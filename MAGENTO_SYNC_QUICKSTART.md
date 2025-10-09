# Magento Sync - Quick Start Guide

## Prerequisites

1. Magento 2 instance with REST API access
2. Integration token from Magento Admin
3. Attributes marked with `is_synced = true` in SPIM database

## Setup (One-Time)

### 1. Get Magento Integration Token

In Magento Admin:
```
System → Integrations → Add New Integration
Name: SPIM Sync
API: Select all or required resources (Catalog, Products, etc.)
Save → Activate → Copy Access Token
```

### 2. Configure SPIM

Add to `.env`:
```bash
MAGENTO_BASE_URL=https://your-magento-store.com
MAGENTO_ACCESS_TOKEN=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### 3. Mark Attributes for Sync

In your database:
```sql
-- Mark specific attributes for sync
UPDATE attributes 
SET is_synced = 1 
WHERE name IN ('name', 'price', 'description', 'sku', 'status');

-- Or mark all for an entity type
UPDATE attributes 
SET is_synced = 1 
WHERE entity_type_id = 1;
```

## Running Sync

### First Sync

```bash
# Step 1: Sync attribute options (required for select/multiselect attributes)
docker exec spim_app php artisan sync:magento:options product

# Step 2: Sync products
docker exec spim_app php artisan sync:magento product
```

### Regular Sync

```bash
# Sync all products
docker exec spim_app php artisan sync:magento product

# Sync one product
docker exec spim_app php artisan sync:magento product --sku=ABC123
```

### Check Results

```bash
# View today's sync logs
docker exec spim_app tail -f storage/logs/magento-sync-$(date +%Y-%m-%d).log

# View all sync logs
docker exec spim_app cat storage/logs/magento-sync.log
```

## What Gets Synced

### From Magento → SPIM

- **New Products**: Creates entity + syncs all attributes (input + versioned)
- **Existing Products**: Updates only INPUT attributes (versioned attributes are read-only from Magento)

### From SPIM → Magento

- **New Products**: Creates in Magento with status=disabled
- **Existing Products**: Updates only VERSIONED attributes where `value_approved != value_live`
- **Override Values**: Uses `value_override` if present, else `value_approved`

## Scheduling (Optional)

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Options sync: daily at 2 AM
    $schedule->command('sync:magento:options product')
             ->dailyAt('02:00');
    
    // Product sync: every 4 hours
    $schedule->command('sync:magento product')
             ->cron('0 */4 * * *');
}
```

Then ensure cron is running:
```bash
* * * * * cd /path-to-spim && php artisan schedule:run >> /dev/null 2>&1
```

## Common Issues

### ❌ "Option sync conflicts detected"

**Problem**: Same option label has different IDs in SPIM vs Magento.

**Solution**: Manually reconcile:
```sql
-- Check SPIM option IDs
SELECT name, allowed_values FROM attributes WHERE data_type IN ('select', 'multiselect');

-- Update in SPIM or Magento to match
UPDATE attributes SET allowed_values = '{"123":"Red","456":"Blue"}' WHERE name = 'color';
```

### ❌ "Attribute validation failed"

**Problem**: An attribute marked for sync cannot be synced (e.g., relationship type).

**Solution**: Unmark the attribute:
```sql
UPDATE attributes SET is_synced = 0 WHERE name = 'category_id';
```

### ❌ "Failed to fetch products: 401 Unauthorized"

**Problem**: Invalid or expired access token.

**Solution**: Generate new token in Magento Admin and update `.env`.

### ❌ "Attribute 'custom_field' not found in Magento"

**Problem**: Attribute exists in SPIM but not in Magento.

**Solutions**:
1. Create the attribute in Magento Admin
2. Or unmark it in SPIM: `UPDATE attributes SET is_synced = 0 WHERE name = 'custom_field'`

## Verification

### Check Sync Stats

Last line of each sync shows stats:
```json
{"created":5,"updated":12,"errors":0,"skipped":3}
```

### Verify Data

```bash
# Check entity was created
docker exec spim_app php artisan tinker
> \App\Models\Entity::where('entity_id', 'ABC123')->first();

# Check attribute values
> $e = \App\Models\Entity::where('entity_id', 'ABC123')->first();
> $e->getAttr('name');
> $e->getAttr('price');
```

### Check value_live

```sql
-- See what's live in Magento
SELECT 
    e.entity_id as sku,
    a.name as attribute,
    v.value_approved,
    v.value_live,
    v.value_override
FROM eav_versioned v
JOIN entities e ON e.id = v.entity_id
JOIN attributes a ON a.id = v.attribute_id
WHERE a.is_synced = 1
AND v.value_approved != v.value_live;
```

## Workflow Example

### Scenario: AI updates product descriptions

1. **AI Pipeline runs**: Updates `value_current` for description attribute
2. **Human reviews**: Approves changes (sets `value_approved = value_current`)
3. **Sync runs**: Detects `value_approved != value_live`
4. **Push to Magento**: Sends updated description
5. **Update tracking**: Sets `value_live = value_approved`

### Scenario: Human overrides AI value

1. **AI Pipeline**: Sets `value_current = "AI Generated Description"`
2. **Human override**: Sets `value_override = "Better Description"`
3. **Sync runs**: Uses `value_override` (takes precedence)
4. **Push to Magento**: Sends "Better Description"
5. **Update tracking**: Sets `value_live = value_override`

## Performance Tips

### Large Catalogs (1000+ products)

- Use single-product sync for urgent updates: `--sku=ABC123`
- Schedule full sync during off-hours
- Monitor sync duration in logs

### Many Attributes

- Only mark essential attributes as `is_synced = true`
- Group related attributes in attribute sets

### Frequent Changes

- Increase sync frequency (e.g., every 2 hours instead of 4)
- Consider webhook-based real-time sync (future enhancement)

## Monitoring

### Daily Check

```bash
# Check for errors in last 24 hours
docker exec spim_app grep ERROR storage/logs/magento-sync-$(date +%Y-%m-%d).log

# Count successful syncs today
docker exec spim_app grep "Completed product sync" storage/logs/magento-sync-$(date +%Y-%m-%d).log | wc -l
```

### Alert on Failures

Add to monitoring system:
```bash
# Alert if sync failed
if grep -q "sync failed" storage/logs/magento-sync.log; then
    # Send alert
fi
```

## Documentation

- **Full Guide**: `docs/magento-sync-implementation.md`
- **Phase 5 Spec**: `docs/phase5.md`
- **Architecture**: `docs/architecture.md`
- **Implementation Summary**: `IMPLEMENTATION_SUMMARY.md`

## Support

Check logs first:
```bash
docker exec spim_app tail -100 storage/logs/magento-sync.log
```

Common log patterns:
- `INFO: Completed` = Success
- `ERROR: Failed to` = API error
- `WARNING: No attributes` = Configuration issue
- `RuntimeException: Option sync conflicts` = Data mismatch

## Next Steps

1. ✅ Configure `.env` with Magento credentials
2. ✅ Mark attributes for sync
3. ✅ Run option sync
4. ✅ Run product sync
5. ✅ Check logs and verify data
6. 🚧 Schedule regular syncs
7. 🚧 Implement monitoring/alerts
8. 🚧 Write tests

---

**Quick Commands Reference**

```bash
# Sync options
docker exec spim_app php artisan sync:magento:options product

# Sync all products
docker exec spim_app php artisan sync:magento product

# Sync one product
docker exec spim_app php artisan sync:magento product --sku=ABC123

# View logs
docker exec spim_app tail -f storage/logs/magento-sync.log
```



