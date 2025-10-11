# Attribute System Refactor - Deployment Guide

## Overview

This deployment migrates from three separate attribute types (`versioned`, `input`, `timeseries`) to a unified `versioned` system with more flexible configuration options.

## Breaking Changes

⚠️ **IMPORTANT**: This is a breaking change that will:
1. Drop the `eav_input` and `eav_timeseries` tables
2. Remove `attribute_type`, `review_required`, and `is_synced` columns from `attributes` table
3. Add new columns: `editable`, `is_pipeline`, `is_sync`, `needs_approval`
4. Update database views

## Pre-Deployment Checklist

- [ ] **Backup database** - This migration makes irreversible changes
- [ ] Confirm no live data in `eav_input` or `eav_timeseries` tables
- [ ] Review all existing attributes and their configurations
- [ ] Test migration on staging/dev environment first

## Migration Details

### Database Changes

**Tables Dropped:**
- `eav_input`
- `eav_timeseries`

**Columns Removed from `attributes`:**
- `attribute_type` (enum: versioned, input, timeseries)
- `review_required` (enum: always, low_confidence, no)
- `is_synced` (boolean)

**Columns Added to `attributes`:**
- `editable` (enum: yes, no, overridable) - default: 'yes'
- `is_pipeline` (enum: yes, no) - default: 'no'
- `is_sync` (enum: no, from_external, to_external) - default: 'no'
- `needs_approval` (enum: yes, no, only_low_confidence) - default: 'no'

**Views Recreated:**
- `entity_attribute_resolved` - Now only uses `eav_versioned`
- `entity_attr_json` - Updated to include approved and live value bags

### Data Migration Mapping

The migration automatically maps old configurations to new:

| Old Config | New Config |
|------------|------------|
| `attribute_type='input'` + `is_synced=true` | `editable='no'`, `is_sync='from_external'` |
| `attribute_type='input'` + `is_synced=false` | `editable='yes'`, `is_sync='no'` |
| `attribute_type='versioned'` + `is_synced=true` | `editable='no'`, `is_sync='to_external'` |
| `attribute_type='versioned'` + `is_synced=false` | `editable='yes'`, `is_sync='no'` |
| `review_required='always'` | `needs_approval='yes'` |
| `review_required='low_confidence'` | `needs_approval='only_low_confidence'` |
| `review_required='no'` | `needs_approval='no'` |

All attributes get `is_pipeline='no'` by default.

## Deployment Steps

### 1. Pre-Deployment

```bash
# Backup database
docker exec spim_db mysqldump -u root -p spim > backup_before_refactor_$(date +%Y%m%d_%H%M%S).sql

# Verify no data in tables to be dropped
docker exec spim_db mysql -u root -p -e "SELECT COUNT(*) FROM spim.eav_input;"
docker exec spim_db mysql -u root -p -e "SELECT COUNT(*) FROM spim.eav_timeseries;"
```

### 2. Run Migration

```bash
# Pull latest code
git pull origin simplified

# Run the migration
docker exec spim_app php artisan migrate --step

# The migration file is: 
# database/migrations/2025_10_10_120000_refactor_attribute_system.php
```

### 3. Verify Migration

```bash
# Check new columns exist
docker exec spim_db mysql -u root -p -e "DESCRIBE spim.attributes;"

# Verify views were recreated
docker exec spim_db mysql -u root -p -e "SHOW FULL TABLES IN spim WHERE TABLE_TYPE LIKE 'VIEW';"

# Check data was migrated correctly
docker exec spim_db mysql -u root -p -e "SELECT name, editable, is_sync, needs_approval FROM spim.attributes LIMIT 10;"
```

### 4. Post-Deployment Testing

- [ ] Test attribute CRUD in admin panel
- [ ] Test entity editing with different editable modes
- [ ] Test approval workflow
- [ ] Test Magento sync (if applicable)
- [ ] Verify read-only fields are disabled in UI
- [ ] Verify overridable fields show helper text

## Rollback Plan

If issues occur, rollback using:

```bash
# Rollback the migration
docker exec spim_app php artisan migrate:rollback --step=1

# Restore from backup (if needed)
docker exec -i spim_db mysql -u root -p spim < backup_before_refactor_YYYYMMDD_HHMMSS.sql
```

## Code Changes Summary

### Core Services Updated:
- `app/Services/EavWriter.php` - New approval logic based on `needs_approval` and `is_sync`
- `app/Services/ReviewQueueService.php` - Uses `needs_approval` instead of `review_required`
- `app/Services/Sync/ProductSync.php` - Handles `is_sync` directions properly
- `app/Services/EntityFormBuilder.php` - Shows editable status, disables read-only fields

### Models Updated:
- `app/Models/Attribute.php` - Validation for configuration rules
- `app/Models/Entity.php` - setAttribute handles editable modes

### UI Updated:
- `app/Filament/Resources/AttributeResource.php` - New fields with real-time validation
- Entity edit forms show appropriate controls for each editable mode

### Documentation Updated:
- `docs/architecture.md` - Complete rewrite of attribute section

## New Configuration Rules

The system now enforces these validation rules:

1. ❌ `(editable='yes' OR editable='overridable') + is_sync='from_external'`
   - Rationale: Attributes synced from external cannot be user-editable

2. ❌ `is_pipeline='yes' + editable='yes'`
   - Rationale: Pipeline attributes should use `editable='overridable'` for manual overrides

3. ❌ `(needs_approval='yes' OR needs_approval='only_low_confidence') + is_sync='from_external'`
   - Rationale: External imports are auto-approved

These rules are enforced at the model level and in the Filament admin UI.

## Support

For issues during deployment:
1. Check migration logs: `docker exec spim_app php artisan migrate --pretend`
2. Review Laravel logs: `docker exec spim_app tail -f storage/logs/laravel.log`
3. Check database state: Use mysql CLI to inspect tables

## Post-Deployment Configuration

After successful deployment, review all existing attributes and adjust their new settings as needed:

```bash
# List all attributes with their new configuration
docker exec spim_app php artisan tinker

# In tinker:
\App\Models\Attribute::all(['name', 'editable', 'is_sync', 'needs_approval', 'is_pipeline'])->toArray()
```

Update any attributes that need different settings than the automated mapping provided.



