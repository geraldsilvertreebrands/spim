Phase 5 — Sync to/from Magento
------------------------------

Goals
- Pull input attributes from Magento into SPIM.
- Push approved versioned attributes from SPIM to Magento.
- Handle product creation in both directions.
- Keep a simple, reliable sync without heavy state machines.
- Use self-consistent batch transactions for data integrity.

Deliverables
- Magento REST API client service
- Attribute option sync command (prerequisite)
- Unified product sync command (pull + push)
- Config per `EntityType` for Magento mapping (attribute set, product_type)
- Attribute type mapping logic (including image upload)
- Sync logs in `storage/logs` plus minimal DB bookkeeping

---

## Connection & Setup

Environment variables:
- `MAGENTO_BASE_URL`: Base URL for Magento instance
- `MAGENTO_ACCESS_TOKEN`: Integration token for REST API

The sync will use Magento 2 REST API exclusively.

---

## Attribute Sync Rules

- **versioned** attributes: sync `value_approved` from SPIM → Magento (write), update `value_live` on success
- **input** attributes: sync from Magento → SPIM (read-only in SPIM)
- **timeseries** attributes: do not sync
- Only attributes with `is_synced = true` participate in sync
- Attribute matching: SPIM attribute `name` must match Magento attribute code exactly

---

## Tasks

### 1) Magento API Client Service

Create `App\Services\MagentoApiClient` with methods:
- `getProducts(array $filters = [])`: fetch products via REST
- `getProduct(string $sku)`: fetch single product
- `createProduct(array $payload)`: create new product
- `updateProduct(string $sku, array $payload)`: update existing product
- `getAttributeOptions(string $attributeCode)`: fetch select/multiselect options
- `createAttributeOption(string $attributeCode, array $option)`: add new option
- `uploadImage(string $imageUrl, string $filename)`: download URL and upload to Magento media

Handle rate limiting, retries, and error responses appropriately.

### 2) Attribute Option Sync Command

**Command**: `php artisan sync:magento:options {entityType}`

Purpose: Bi-directionally sync select/multiselect attribute option values between SPIM and Magento. This must run successfully before product sync, as mismatched options will cause product sync failures.

Algorithm:
1. Find all `is_synced = true` attributes for the entity type where `data_type IN ('select', 'multiselect')`
2. For each attribute:
   - Fetch Magento options (label + option_id)
   - Compare with SPIM `allowed_values` (stored as JSON array of options)
   - Detect conflicts:
     - Same label, different IDs → fatal error (log and report)
     - Different labels, same ID → fatal error (log and report)
   - Sync missing options:
     - Options in Magento but not SPIM → add to SPIM `allowed_values`
     - Options in SPIM but not Magento → create in Magento via API
3. Transaction scope: one transaction per attribute's options
4. Log all changes and conflicts

This command:
- Can be triggered on-demand
- Runs automatically before scheduled product sync
- NOT run before single-product on-demand syncs

### 3) Product Sync Command

**Command**: `php artisan sync:magento {entityType} [--sku=SKU]`

Options:
- `--sku`: Optional. Sync only a specific product by SKU. Useful for on-demand syncs.

Purpose: Unified command that pulls input attributes from Magento and pushes approved versioned attributes to Magento.

#### Sync Workflow:

**Step 0: Validation**
- Validate all `is_synced = true` attributes for this entity type
- Check attribute name matching (SPIM `name` = Magento attribute code)
- Check type compatibility (can SPIM data_type map to Magento attribute type?)
- Fatal error with clear reporting if any attribute cannot sync
- Skip option validation (handled by separate command)

**Step 1: Pull from Magento → SPIM**

For products in Magento not in SPIM:
- Match by: Magento SKU = SPIM `entity_id`
- Create `entities` record with `entity_type_id` and `entity_id` = SKU
- For all `is_synced = true` attributes:
  - **input** attributes: write to `eav_input` via `EavWriter::upsertInput()`
  - **versioned** attributes: write to `eav_versioned`, setting `value_current`, `value_approved`, AND `value_live` all to the same value (no approval required on initial import)
- Transaction scope: one transaction per product

For existing products (in both systems):
- Sync only **input** attributes from Magento → SPIM
- Update `eav_input` values via `EavWriter::upsertInput()`
- Transaction scope: one transaction per product

**Step 2: Push from SPIM → Magento**

For products in SPIM not in Magento:
- Create product in Magento via REST API
- Set all synced attributes:
  - **versioned** attributes: use `value_approved` (or `value_override` if present)
  - **input** attributes: send current value from `eav_input`
- Set `status = disabled` UNLESS 'status' is a synced attribute
- After successful creation, update `value_live = value_approved` for all versioned attributes
- Transaction scope: one transaction per product for DB updates

For existing products (in both systems):
- Find **versioned** attributes where `value_approved != value_live` AND `is_synced = true`
- Apply `value_override` if present (override takes precedence)
- Push updates to Magento via REST API
- On success: set `value_live = value_approved` (or `value_override`)
- Transaction scope: one transaction per product

**Step 3: Logging**
- Log sync summary: products created, updated, errors
- Log to `storage/logs/magento-sync-{date}.log`
- Track sync run metadata: start time, end time, entity count, error count

#### Attribute Type Mapping

Basic mappings:
- `integer` → Magento integer/decimal
- `text` → Magento text/varchar
- `html` → Magento textarea (with wysiwyg)
- `select` → Magento select (options must match)
- `multiselect` → Magento multiselect (options must match)
- `belongs_to` / `belongs_to_multi` → not synced (internal relationships)

Special handling:
- **Images (media attributes)**: 
  - SPIM stores as URL in text field
  - When pushing to Magento: 
    1. Download image from URL
    2. Upload via Magento media API (`/rest/V1/products/{sku}/media`)
    3. Store returned media path/ID
  - When pulling from Magento: store media URL as-is

### 4) Scheduling

Add to `app/Console/Kernel.php`:

```php
// Run option sync before product sync (e.g., daily at 2 AM)
$schedule->command('sync:magento:options product')->dailyAt('02:00');

// Run product sync (e.g., every 4 hours)
$schedule->command('sync:magento product')->cron('0 */4 * * *');
```

Optional at first; can be triggered manually.

### 5) Tests

**Unit tests**:
- Attribute type mapping logic
- Option conflict detection
- Data transformation between SPIM and Magento formats
- Image URL to media upload payload conversion

**Feature tests** (using `Http::fake()`):
- **Option sync**:
  - Mock Magento option responses
  - Assert correct options added to SPIM `allowed_values`
  - Assert correct API calls to create missing Magento options
  - Assert conflicts are detected and reported
  
- **Product pull**:
  - Mock Magento product responses
  - Assert `entities` records created
  - Assert `eav_input` and `eav_versioned` populated correctly
  - Assert initial import sets all three value fields (`value_current`, `value_approved`, `value_live`)
  
- **Product push**:
  - Setup: create entities with `value_approved != value_live`
  - Mock Magento update API
  - Assert correct payloads sent
  - Assert `value_live` updated on success
  - Assert `value_override` takes precedence when present

Test fixtures:
- Attributes marked `is_synced = true` with various `data_type` and `attribute_type` combinations
- Entities with pending approvals (`value_approved != value_live`)
- Entities with overrides (`value_override != null`)

---

## Acceptance Criteria

- ✅ Option sync command runs successfully and syncs options bi-directionally
- ✅ Option sync detects and reports conflicts without corrupting data
- ✅ Product sync pulls input attributes from Magento and creates entities as needed
- ✅ Product sync pushes approved versioned attributes to Magento
- ✅ Product sync creates products in Magento when they exist only in SPIM
- ✅ Product sync creates products in SPIM when they exist only in Magento
- ✅ Initial product import sets `value_current`, `value_approved`, and `value_live` identically
- ✅ `value_live` accurately tracks what's in Magento after push
- ✅ Only `is_synced = true` attributes participate in sync
- ✅ Attribute validation fails fast with clear error messages
- ✅ Image attributes upload properly to Magento media API
- ✅ Single-product sync works via `--sku` flag
- ✅ Transactions ensure data consistency per product/batch
- ✅ Comprehensive logging for debugging and audit

---

## Environment

- `.env` contains `MAGENTO_BASE_URL` and `MAGENTO_ACCESS_TOKEN`
- `.env.testing` uses MySQL 8
- Tests use `Http::fake()` to ensure no real outbound calls
- Optional: `MAGENTO_FAKE=true` flag to switch to local stub server in dev

---

## Implementation Notes

1. Start with products only; categories and brands follow similar patterns
2. Build abstract base sync class for shared logic
3. Use self-consistent transactions (per product) rather than one giant transaction
4. Log verbosely for debugging; sync issues are easier to fix with good logs
5. Consider rate limiting and batch sizes for large catalogs
6. Handle Magento API errors gracefully (retry transient failures, log permanent failures)
7. For image sync, validate URLs before attempting download/upload

---

## Implementation Status

### ✅ Completed Components

**Services:**
- `app/Services/MagentoApiClient.php` - REST API client with retry logic, error handling, and all required methods
- `app/Services/Sync/AbstractSync.php` - Base class with logging and stats tracking
- `app/Services/Sync/AttributeOptionSync.php` - Bi-directional option sync with conflict detection
- `app/Services/Sync/ProductSync.php` - Full product sync (pull + push) with validation

**Console Commands:**
- `app/Console/Commands/SyncMagentoOptions.php` - Command for syncing attribute options
- `app/Console/Commands/SyncMagento.php` - Command for syncing products with optional SKU filter

**Configuration:**
- `config/services.php` - Added Magento configuration (base_url, access_token)
- `config/logging.php` - Added dedicated magento-sync log channel
- `.env.example` - Added MAGENTO_BASE_URL and MAGENTO_ACCESS_TOKEN

### 📋 Usage

```bash
# Sync attribute options (run before first product sync or when options change)
php artisan sync:magento:options product

# Sync all products for an entity type
php artisan sync:magento product

# Sync a specific product by SKU
php artisan sync:magento product --sku=ABC123
```

### 🔧 Configuration Required

Add to your `.env` file:
```
MAGENTO_BASE_URL=https://your-magento-site.com
MAGENTO_ACCESS_TOKEN=your_integration_token_here
```

### 📝 Logs

Sync logs are written to `storage/logs/magento-sync.log` with daily rotation (14 day retention).

### ⚠️ Important Notes

1. **Option sync must run successfully before product sync** for attributes with select/multiselect data types
2. **Transactions are per-product**, not global - partial syncs are possible
3. **Image uploads** will download from URL and upload to Magento media API (not yet implemented - TODO)
4. **Override values** take precedence over approved values when pushing to Magento
5. **Initial imports** set all three value fields (`value_current`, `value_approved`, `value_live`) identically

### 🚧 Future Enhancements (Not Yet Implemented)

1. **Image attribute handling** - Download and upload media files (currently treated as text)
2. **Batch processing** - Process products in batches for better performance on large catalogs
3. **Progress indicators** - Show progress for long-running syncs
4. **Dry-run mode** - Preview changes without applying them
5. **Category and brand sync** - Similar to product sync but for other entity types
6. **Webhook support** - Real-time sync triggers from Magento
7. **Conflict resolution UI** - Interface for resolving attribute option conflicts
