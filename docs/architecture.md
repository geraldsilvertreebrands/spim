SilvertreePIM — Architecture & Intention
---------------------------------------

# Conceptual approach

SilvertreePIM is a tool for managing product and related content, where AI transformations are the principal way in which content will be updated and managed, and where the code is as lean as possible and customisation is done by code edits (using AI) rather than an over-engineered platform to allow all sorts of config-driven customisation.

Key concepts include:

## Entities

These are "things" with attributes. Each entity type has a corresponding set of attributes.

Entity types include:
- products, with a type per Magento attribute set
- categories
- brands
- scraped products
- supplier-provided products
- supplier ranges, grouped together for e.g., approval processes
- ingredients, for ingredient information pages.

Each entity has a stable natural identifier, `entity_id`, that matches the external system’s identifier for that entity type. It is unique per entity type.

Entities are related to each other with relationships of various types, e.g., "belongs_to", "related_to", etc.

## Pipelines

Pipelines are automation chains that derive a single attribute's value from other attributes on the same entity. Each pipeline owns a 1:1 relationship with an attribute (`attributes.pipeline_id`) and is composed of one source module followed by one or more processor modules. Modules subclass `AbstractPipelineModule` which provides registration hooks, Filament form configuration, settings persistence, `getInputAttributes()` discovery, and a `process(PipelineContext $context): PipelineResult` contract. The mutable state passed between modules is held in a `PipelineContext` object (entity inputs, pipeline metadata) while module outputs are expressed as immutable `PipelineResult` instances containing `value`, `confidence`, `justification`, optional `meta`, and a status flag.

Module settings are stored in `pipeline_modules` with ordering information, PHP class identifiers, JSON configuration, and timestamps. The parent `pipelines` row tracks ownership (`attribute_id`, `entity_type_id`), `pipeline_version`, `pipeline_updated_at`, execution aggregates, and queue metadata. Any module configuration change bumps the version/timestamp so downstream jobs can detect stale attribute values. Pipeline execution is triggered when source inputs change (`PipelineTriggerService`), on nightly batch runs (`RunNightlyPipelines` scheduled at 2 AM), or manually from the pipeline UI. Runs execute through the queue in bounded batches (default 200 entities, limited parallelism) and abort on the first entity failure.

**Available Modules:**
- **AttributesSourceModule** (source): Loads attribute values from specified attributes as pipeline inputs
- **AiPromptProcessorModule** (processor): OpenAI integration with JSON schema templates (text, integer, boolean, array, or custom schemas)
- **CalculationProcessorModule** (processor): JavaScript execution via sandboxed Node.js helper with batched processing

**Dependency Management:** `PipelineDependencyService` uses Kahn's algorithm to detect circular dependencies and compute execution order. Pipelines that depend on other pipeline outputs must run after their dependencies.

**Evaluation Testing:** Eval cases are stored per pipeline/entity in `pipeline_evals` and record the desired output plus the most recent actual result, justification, confidence, and the input hash used. Nightly and on-demand runs always recompute evals, even if inputs are unchanged, to expose drift when upstream models evolve. Run metadata is captured in `pipeline_runs` (status, trigger, totals, token counts, timings) and linked back to attribute updates through `eav_versioned` fields (`input_hash`, `pipeline_version`, `justification`, `confidence`).

**UI:** Full Filament interface for creating and managing pipelines. Create page selects entity type and target attribute. Edit page features tabbed interface with module builder (drag-to-reorder, dynamic forms), statistics (run history, token usage), and evaluation management (test cases with pass/fail tracking). See `PIPELINE_UI_GUIDE.md` for user documentation.

## Syncs

We sync attributes for products (and other entities) to Magento. In general, this system is the master for attribute values, but Magento is the master for attributes which are not included in this system (e.g., inventory data), and we can sync starting attribute values from Magento into SPIM.

## Attributes

All attributes use a unified "versioned" structure (previously there were separate types). The master `attributes` table stores all configuration, uniquely identified by `entity_type_id` and attribute `name`. Properties include:

- **data_type**: One of integer, text, html, json, select, multiselect, belongs_to, belongs_to_multi
- **editable**: Controls how users can modify the attribute:
  - `yes`: Directly editable - sets value_current (and possibly value_approved/value_live based on needs_approval and is_sync)
  - `no`: Read-only - cannot be edited by users (typically synced from external or static data)
  - `overridable`: Shows current value as read-only, allows setting an override value
- **is_pipeline**: `yes` | `no` - Whether this attribute is generated by AI/automation pipelines (placeholder for future implementation)
- **is_sync**: Controls synchronization with external systems:
  - `no`: SPIM only, not synced
  - `from_external`: Read from Magento (updates value_current, value_approved, and value_live)
  - `to_external`: Write to Magento (syncs value_approved → Magento → updates value_live)
- **needs_approval**: Controls the approval workflow:
  - `yes`: Always requires human approval before value_approved is set
  - `only_low_confidence`: Requires approval only when confidence < 0.8
  - `no`: Auto-approves all changes (immediately sets value_approved)
- **allowed_values**: For select and multiselect attributes, available options (key-value pairs)
- **linked_entity_type**: For belongs_to fields, the entity_type_id that the field refers to. Storage uses a link table (`entity_attr_links`) rather than comma-separated IDs.
- **ui_class**: Optional custom UI handler class for displaying/editing the attribute

### Configuration Rules

Certain combinations are not allowed and will fail validation:
1. ❌ `(editable='yes' OR editable='overridable') + is_sync='from_external'` - External-synced attributes cannot be user-editable
2. ❌ `is_pipeline='yes' + editable='yes'` - Pipeline attributes should use `editable='overridable'` for manual overrides
3. ❌ `(needs_approval='yes' OR needs_approval='only_low_confidence') + is_sync='from_external'` - External imports are auto-approved

### UI Class Interface

The ui_class object is a pluggable interface for:
- Displaying the attribute value in a table (summarized)
- Displaying in a detail view page (full value, readonly), including justification and metadata
- Providing custom editing interface
- Handling data from the editing UI and converting to storage format

If not specified, falls back on a default class that renders the value appropriately for its data_type.

## Attribute Value Storage

All attributes now use a unified EAV structure in the `eav_versioned` table. Each attribute value has:

- **value_current**: Latest value, not necessarily approved. This is always set when editing.
- **value_approved**: Value approved for sync (but not yet synced to external systems)
- **value_live**: The value as currently synced to external systems
- **value_override**: If not null, a human-forced override value. The "current" value is still tracked separately.
- **updated_at**: Timestamp of last modification
- **input_hash**: Hash of input data used by pipelines to detect changes
- **justification**: AI-generated explanation for the current value
- **confidence**: Score 0..1 for AI-generated values
- **meta**: JSON field for additional metadata

### Value Flow

**Writing Values (via EavWriter service):**

`App\Services\EavWriter` is the canonical way to write attribute values. It enforces all business rules:

**upsertVersioned(entityId, attributeId, value, options):**
1. Always sets `value_current`
2. Auto-approval logic based on `needs_approval`:
   - `'no'` → also sets `value_approved`
   - `'only_low_confidence'` → sets `value_approved` if confidence ≥ 0.8
   - `'yes'` → does NOT set `value_approved` (requires manual approval)
3. If auto-approved AND `is_sync='no'` → also sets `value_live`
4. Accepts options: `input_hash`, `justification`, `confidence`, `meta`

**setOverride(entityId, attributeId, value):**
- Only sets `value_override` (current value unchanged)
- Used for `editable='overridable'` attributes
- Approval workflow will later move override → approved

**approveVersioned(entityId, attributeId):**
1. Sets `value_approved = value_override ?? value_current`
2. If `is_sync='no'` → also sets `value_live`
3. If `is_sync='to_external'` → sync will update `value_live` after Magento confirms

**bulkApprove(items):**
- Approves multiple attributes at once
- Used by review queue for bulk operations

All methods handle upserts properly (won't overwrite `created_at` on updates).

### Sync Behavior

During Magento sync:
- **Initial import** (new products): ALL synced attributes (both `from_external` and `to_external`) are imported and all three value fields are set identically
- **Subsequent imports**: Only `is_sync='from_external'` attributes are updated from Magento
- **Export to Magento**: Only `is_sync='to_external'` attributes where `value_approved != value_live` are synced

## Resolved values and JSON bags

To avoid dynamic SQL pivots, we use MySQL views to pre-aggregate values into per-entity JSON objects:
- `entity_attribute_resolved`: Resolves values from `eav_versioned`, computing both override and current-only values
- `entity_attr_json`: Aggregates resolved values into JSON bags per entity:
  - `attrs_with_override`: Uses value_override if present, else value_current
  - `attrs_current`: Always uses value_current
  - `attrs_approved`: Uses value_approved
  - `attrs_live`: Uses value_live

Relations (`belongs_to`, `belongs_to_multi`) are stored in `entity_attr_links` and can be exposed via a companion aggregation view when needed.

## Tech architecture
	•	Core app: Laravel (PHP) + Filament admin for back‑office UI and API.
	•	Worker(s) (later phases): Python service for scraping (Scrapy) and AI pipeline execution (FastAPI/Celery optional).
	•	Storage: MySQL
	•	Integration: Magento 2 via REST; thin Magento module only if/where APIs fall short.
	•	Secrets: env vars for now (12‑factor); revisit KMS/SM when needed.

---

# Magento sync

We will sync products, categories, and maybe more. Each sync type is its own logic, as they are fairly similar, though based on an abstract parent sync class.

## Connection and setup:
- .env file contains vars MAGENTO_BASE_URL and MAGENTO_ACCESS_TOKEN
- use Magento 2 REST API

## Sync workflow:
0. Transaction scope: One transaction per product (not global) to allow partial syncs without corruption.
1. Match up all attributes that have `is_sync` IN ('from_external', 'to_external'). Matching is done on attribute name being identical. Validate that attributes can be synced (no relationship types). Any missing/unsyncable attributes cause a fatal error at this stage.
2. Sync select and multiselect attribute value options from Magento to SPIM (sync keys and values), and from SPIM to Magento (bi-directional). Fatal error on impossible syncs, e.g., same key different values, different keys same value, etc.
3. **Pull from Magento → SPIM**:
    - For new products (in Magento, not in SPIM): Create entity record and import ALL synced attributes (both `from_external` and `to_external`), setting all three value fields (`value_current`, `value_approved`, `value_live`) to the imported value
    - For existing products: Only update attributes with `is_sync='from_external'`, setting all three value fields
4. **Push from SPIM → Magento**:
    - For new products (in SPIM, not in Magento): Create product in Magento with status=disabled unless status is synced. Send all synced attributes using `value_override ?? value_approved`. Update `value_live` on success.
    - For existing products: Find attributes with `is_sync='to_external'` where `value_approved != value_live`. Send updates using `value_override ?? value_approved`. Update `value_live` on success.

## Attribute type mapping:
- in general, most mappings are obvious
- images (media) stored as URLs, when syncing to Magento: upload the image to Magento. When syncing from Magento, keep the URL as-is,

## Sync Architecture (Queue-Based)

All sync operations are asynchronous and queue-based to support long-running operations, retries, and background processing.

### Commands vs Jobs

**Artisan Commands** (user-facing):
- `sync:magento:options {entityType}` - Queue attribute option sync
- `sync:magento {entityType} [--sku=]` - Queue full product sync or single product
- `sync:cleanup [--days=30]` - Delete old sync results

Commands **dispatch jobs** to the queue rather than executing sync logic directly. This ensures:
- Fast command response (immediate return)
- Background execution via queue workers
- Automatic retry on failure
- No timeout issues for large syncs

**Queue Jobs** (background workers):
- `App\Jobs\Sync\SyncAttributeOptions` - Sync select/multiselect options
- `App\Jobs\Sync\SyncAllProducts` - Sync all products for an entity type
- `App\Jobs\Sync\SyncSingleProduct` - Sync a single product by entity

Jobs create `SyncRun` records, execute the sync service, and update the run with results.

### Sync Services

Sync logic is in service classes under `App\Services\Sync\`:
- `AttributeOptionSync` - Bi-directional option sync (Magento is source of truth)
- `ProductSync` - Full product sync (pull from Magento, push to Magento)
- `AbstractSync` - Base class with logging and stats tracking

Services are **stateless** and **reusable** - they can be called from jobs, commands, or tests.

### SyncRun Tracking

Every sync operation creates a `SyncRun` record to track execution:

**sync_runs** table:
- `entity_type_id` - Which entity type (product, category, etc)
- `sync_type` - Type of sync: 'options', 'products', etc
- `started_at`, `completed_at` - Timestamps
- `status` - Enum: 'running', 'completed', 'failed', 'partial', 'cancelled'
- `triggered_by` - Source: 'user', 'schedule', 'cli', 'api'
- `user_id` - Which user triggered (null for schedule)
- `total_items`, `successful_items`, `failed_items`, `skipped_items` - Statistics
- `error_summary` - Overall error message if failed

Status values:
- **running**: Sync in progress
- **completed**: All items succeeded
- **partial**: Some items failed, some succeeded (errors > 0)
- **failed**: Fatal error, sync aborted
- **cancelled**: User cancelled the sync

### SyncResult Tracking

Each item (product, attribute option, etc) in a sync gets a `SyncResult` record:

**sync_results** table:
- `sync_run_id` - Parent sync run
- `entity_id` - Optional: which entity (for product syncs)
- `attribute_id` - Optional: which attribute (for option syncs)
- `item_identifier` - Human-readable: SKU or attribute name
- `operation` - Enum: 'create', 'update', 'skip', 'validate' (optional)
- `status` - Enum: 'success', 'error', 'warning'
- `error_message` - Error details if failed
- `details` - JSON: before/after values, API response, metadata
- `created_at` - When this result was logged

This provides:
- Detailed audit trail
- Per-item error messages
- Ability to retry individual items
- Historical sync data for analysis

### SyncRunService Wrapper

`App\Services\Sync\SyncRunService` wraps sync execution with proper lifecycle management:

```php
$syncRunService->run(
    syncType: 'products',
    entityType: $entityType,
    userId: $userId,
    triggeredBy: 'user',
    runner: function($syncRun) {
        $sync = new ProductSync(..., syncRun: $syncRun);
        return $sync->sync(); // Returns ['stats' => [...]]
    }
);
```

The wrapper:
1. Creates `SyncRun` with status='running'
2. Calls the runner function
3. Updates `SyncRun` with final status and stats
4. Handles exceptions and marks as 'failed' if needed
5. Returns the `SyncRun` record

This ensures **consistent tracking** even if sync logic throws exceptions.

### Trigger Sources

Syncs can be triggered from multiple sources:

- **user**: Manual trigger from Filament UI by a logged-in user
- **schedule**: Automated via Laravel scheduler (cron)
- **cli**: Manual command-line execution (testing, maintenance)
- **api**: Future: API endpoint triggers

The `triggered_by` and `user_id` fields track provenance for audit purposes.

### Error Handling Strategy

Syncs use **graceful degradation**:
- Individual item failures don't abort the entire sync
- Status becomes 'partial' if any items fail
- Each failure logged to `sync_results` with detailed error
- Sync continues processing remaining items

Example: Syncing 100 products where 3 fail:
- SyncRun status: 'partial'
- total_items: 100
- successful_items: 97
- failed_items: 3
- 3 SyncResult records with status='error' and error_message

### Data Type Validation

ProductSync validates attribute data type compatibility between SPIM and Magento:
- Calls `MagentoApiClient::getAttribute()` for each synced attribute
- Checks SPIM data_type against Magento's frontend_input/backend_type
- Throws RuntimeException on incompatible types (blocks sync)
- Logs warnings for questionable but allowed combinations

This prevents data corruption from type mismatches.

---

# Implementation plan

## Phase 0: Project infrastructure
0.1 Laravel setup, Filament, etc and docker configs, with sane PHP defaults for local docker development
0.2 Access control and user accounts and simple role access levels

## Phase 1: Database and models
1.1 Set up database schema and migrations (no seeders needed, as we'll use real data for testing, except we need user accounts)
1.2 Create basic models for the entities, attributes

## Phase 2: UI and infrastructure for attributes
2.1 Basic filament UI
2.2 Attribute CRUD and config
2.3 Create an interface and base class for viewing/editing attributes: method for the current value (return HTML probably), interface for editing (HTML) and method for saving the results from the editing interface. Then, allow overrides in ui_class field for attributes.

## Phase 3: Entity browsing
3.1 UI menu item for each entity type
3.2 For each entity type, a "listing" page with configurable column choice (remembered), searchable and sortable. Use the ui_class field to render attributes, if required
3.3 Entity view page, using ui_class. Structure as a form with a column for attribute names (and some supporting icons: attribute_type, whether current version is synced or not, whether overridden). Then on the right, a column of the values using the ui_class to render each value. Ideally, have this as a slide out (or similar idea) from the right, so that the main product list is still a little visible, and it's quick to click between items in the list without closing, reopening the edit window each time
3.4 Button on each versioned attribute, to "override", which exposes editing UI and save button.

## Phase 4: Approval workflow
4.1 Table of entities needing approval (ie approved value differs from current value), with list of attributes changed for each
4.2 Click to review and approve changes, and bulk approval tickboxes.

## Phase 5: Sync to/from Magento
5.1 Sync from Magento for input attributes
5.2 Sync to Magento for versioned attributes. Sync code needs to handle mapping between our and their data_types, eg we might store something as text, and Magento as a select, in which case we need to add attribute option values.

## Phase 6: Pipelines ✅
Create, edit, run and review pipeline runs. Full implementation complete with UI, services, jobs, and testing.

## Roadmap References
- Phase 2 — Attributes UI (`docs/phase2.md`)
- Phase 3 — Entity browsing (`docs/phase3.md` and `docs/phase3-summary.md`)
- Phase 4 — Approval workflow (`docs/phase4.md` and `docs/phase4-summary.md`)
- Phase 5 — Magento sync (`docs/phase5.md`)
- Phase 6 — Pipelines (`docs/phase6.md`)

⸻

# UI structure (partial)
Menu:
- Dashboard
- Review
- Entities
    - (one per product type)
- Attributes
    - Product types
    - Attributes (with a selector to choose product type)
- Settings
    - Pipelines
    - Syncs


⸻

# Data model

## Core catalogue

**attributes**
- id (PK)
- entity_type_id (FK)
- name (unique per entity_type)
- display_name, attribute_section_id, sort_order
- data_type (integer|text|html|json|select|multiselect|belongs_to|belongs_to_multi)
- editable (yes|no|overridable)
- is_pipeline (yes|no)
- is_sync (no|from_external|to_external)
- needs_approval (yes|no|only_low_confidence)
- allowed_values (JSON, for select/multiselect)
- linked_entity_type_id (FK, for belongs_to types)
- ui_class (optional custom UI handler)
	
**attribute_sections**
- id (PK)
- entity_type_id (FK)
- name
- sort_order

**entity_types**
- id (PK)
- name
- description

**entities**
- id (PK, ULID)
- entity_id (natural ID from external system, unique per entity_type)
- entity_type_id (FK)

**eav_versioned** (all attributes use this now)
- id (PK)
- entity_id (FK)
- attribute_id (FK)
- value_current (latest value)
- value_approved (approved value, ready for sync)
- value_live (synced value)
- value_override (human override)
- input_hash (for pipeline change detection)
- justification (AI explanation)
- confidence (0..1)
- meta (JSON)

**sync_runs** (track sync operations)
- id (PK)
- entity_type_id (FK)
- sync_type (enum: 'options', 'products', etc)
- started_at, completed_at (timestamps)
- status (enum: 'running', 'completed', 'failed', 'partial', 'cancelled')
- triggered_by (enum: 'user', 'schedule', 'cli', 'api')
- user_id (FK, nullable - null for scheduled syncs)
- total_items, successful_items, failed_items, skipped_items (integers)
- error_summary (text, nullable)

**sync_results** (per-item sync results)
- id (PK)
- sync_run_id (FK)
- entity_id (ULID, nullable - for product syncs)
- attribute_id (FK, nullable - for option syncs)
- item_identifier (string - SKU or attribute name for display)
- operation (enum: 'create', 'update', 'skip', 'validate', nullable)
- status (enum: 'success', 'error', 'warning')
- error_message (text, nullable)
- details (JSON, nullable - before/after values, API response, etc)
- created_at (timestamp)

## Scraping & matching

scrape_sources
	•	id PK, name, description, scrape_cron, last_scraped

scrape_runs
	•	id PK, scrape_source_id, started_at, ended_at, num_products, result_message

scrape_products (slow‑changing latest snapshot per URL)
	•	id PK, sku?, url, name, description, short_description
	•	brand, category, image_url, other_images (text[]), other_data (json), barcodes (text[])
	•	updated_at, created_at

scrape_product_data (fast‑changing)
	•	id FK, scraped_at ts, scraped_date date, price, list_price, in_stock bool, stock_qty

scrape_brand_matches / scrape_category_matches
	•	normalise scraped brand/category → internal category tree; include match_confidence float

scrape_product_matches
	•	id FK, product_id FK, match_confidence float, match_status smallint
	•	match_status: 1 AI match, 2 human match, -1 human forced not a match, 0 not a match

## Pipelines

**pipelines**
- id (PK, ULID)
- attribute_id (FK, unique - 1:1 with attributes)
- entity_type_id (FK)
- name (nullable string, optional friendly label)
- pipeline_version (unsigned integer, auto-incremented on module changes)
- pipeline_updated_at (timestamp)
- last_run_at, last_run_status, last_run_duration_ms (nullable)
- last_run_processed, last_run_failed, last_run_tokens_in, last_run_tokens_out (nullable integers)
- created_at, updated_at

**pipeline_modules**
- id (PK, ULID)
- pipeline_id (FK, cascade delete)
- order (unsigned smallint, unique per pipeline)
- module_class (string FQCN, e.g., `App\Pipelines\Modules\AttributesSourceModule`)
- settings (JSON, module-specific configuration)
- created_at, updated_at

**pipeline_runs**
- id (PK, ULID)
- pipeline_id (FK)
- pipeline_version (unsigned integer, snapshot of version at run time)
- triggered_by (enum: `schedule`, `entity_save`, `manual`)
- trigger_reference (nullable string, entity ID or user ID)
- status (enum: `running`, `completed`, `failed`, `aborted`)
- batch_size, entities_processed, entities_failed, entities_skipped
- tokens_in, tokens_out (nullable integers, AI module usage)
- started_at, completed_at
- error_message (nullable text)
- created_at

**pipeline_evals**
- id (PK, ULID)
- pipeline_id (FK, cascade delete)
- entity_id (FK entities, unique per pipeline)
- input_hash (string 64, SHA-256 of input data)
- desired_output (JSON, expected result)
- notes (nullable text, documentation)
- actual_output (JSON, nullable, most recent result)
- justification (nullable text, from last run)
- confidence (nullable decimal 5,4)
- last_ran_at (nullable timestamp)
- created_at, updated_at

**eav_versioned** (extended for pipelines)
- ... existing fields ...
- pipeline_version (unsigned integer, nullable, tracks which version generated this value)
- input_hash (string 64, nullable, SHA-256 of source inputs)
