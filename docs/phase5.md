Phase 5 — Sync to/from Magento
------------------------------

Goals
- Pull input attributes from Magento into SPIM.
- Push approved versioned attributes from SPIM to Magento.
- Keep a simple, reliable sync without heavy state machines.

Deliverables
- Config per `EntityType` for Magento mapping (attribute set, attribute codes)
- Importer job(s): fetch products/categories/brands and map to input attributes
- Exporter job(s): detect changed approvals and push
- Sync logs in `storage/logs` plus minimal DB bookkeeping fields

Tasks
1) Config & mapping
  - Extend `entity_types.external_platform_config` (json) with:
    - magento: { attribute_set, product_type (if needed), attribute_code_map: { spim_name: magento_code } }
  - Add per-attribute override for Magento code if needed

2) Import (read) pipeline
  - Command: `php artisan sync:magento:pull {entityType}`
  - Fetch via REST (products first)
  - Map fields → write to `eav_input` via `EavWriter::upsertInput`
  - Backfill `entities` records by `entity_id`

3) Export (write) pipeline
  - Command: `php artisan sync:magento:push {entityType}`
  - Query attributes with `value_approved != value_live` and `attributes.is_synced = 1`
  - Map to Magento codes and push via REST
  - On success: set `value_live = value_approved`

4) Scheduling
  - Add Laravel scheduler entries for regular pull/push (optional at first)

5) Error handling & retries
  - Log failures; mark rows for retry
  - Simple exponential backoff in job dispatcher

6) Tests (integration-light)
  - Mock Magento client; verify mapping and persistence

Acceptance criteria
- Pull sync writes input attributes correctly and creates entities as needed.
- Push sync updates Magento and advances `value_live` accurately.
- Only attributes flagged `is_synced` participate.

Open questions
- Use SKU or numeric ID for `entity_id`? Choose per type and document in `entity_types`.
- How to handle select-type mapping (labels vs option IDs)? Suggest: maintain a small mapping table per attribute if Magento requires option IDs.
