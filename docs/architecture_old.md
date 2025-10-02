SilverteePIM — Architecture & Intention
---------------------------------------

# Conceptual approach

SilvertreePIM is a tool for managing product and related content, where AI transformations are the principle way in which content will be updated and managed, and where the code is as lean as possible and customisation is done by code edits (using AI) rather than an over-engineered platform to allow all sorts of config-driven customisation.

Key concepts include:

## Entities

These are "things" with attributes. Each entity type has a corresponding DB table (with an overall entities table), with a column per attribute. This means that adding/removing attributes is a database schema change, but this is low cost and allows a simpler database schema and more flexibility than a full EAV system.

Entity types include:
- products, with a type per Magento attribute set
- categories
- brands
- scraped products
- supplier-provided products
- supplier ranges, grouped together for e.g., approval processes
- ingredients, for ingredient information pages.

Entities are related to each other with relationships of various types, e.g., "belongs to", "related to", etc.

## Pipelines

These are AI or automation pipelines that take a set of attributes, from the current entity or related entities, and produce an updated value of an attribute for an entity. Pipelines run when source attribute values change. 

Associated with pipelines are "evals", which are example outputs, used for refining AI prompts and ensuring that outputs don't change over time, similar in concept to unit or acceptance tests.

Pipelines also track stats like token usage, etc.

## Syncs

We sync attributes for products (and other entities) to Magento. In general, this system is the master for attribute values, but Magento is the master for attributes which are not included in this system (e.g., inventory data), and we can sync starting attribute values from Magento into SPIM.

## History and approval flows for attributes

On entity tables, we store mulitple rows per entity, corresponding to history or approval flows. So, we might have a row that is the currently live values, and one for proposed amendments from AI pipelines that are awaiting approvals. There may also be an "override" row that has non-null values for values that are manually overridden compared to their AI-generated values.

## Attributes

A table of attributes manages properties for attributes across all entity types, uniquely identified by entity_type_id and attribute name. Properties include:
- type: One of integer, text, html, json, select, multiselect
- require_approval: whether automated updates to an attribute need approval before going live
- is_readonly: if true, synced from external system, but not editable locally and not synced back to remote system
- allowed_values: for select and multiselect attributes, available options

## History tracking and approval flows

Each row in entity type table has a status, one of:
- override: Non-null values are human forced overrides
- updated: Values changed, not yet approved
- approved: Approved, not yet synced to a live system (where relevant)
- live: Synced and live on external system
- archive: Old row for history purposes only

When making edits to a row, here is the algorithm that models need to follow:
1) Pipeline edits (ie automated edits):
- take latest row that is status "updated", failing which "approved", failing which "live"
- calculate new value. Stop if it's the same as that row already
- depending on status and attribute require_approval value:
    - updated: copy and update row if edited_date is before today, else update existing row
    - approved: 
        - if require_approval: create new row with status "updated", and update it
        - if not require_approval: copy and update row if edited_date is before today, else update existing row
    - live: copy and create new row, with status "approved" if not require_approval, else status "updated"
- cleanup after edits: 
    - look for multiple rows with the same status for the same sku, and change older ones to status "archive"
    - look for identical rows with status "updated", "approved", and/or "live", and update the newer one to status the highest of "live", "approved", "updated" that is present, and the older one to "archive"

2) Human edits:
- create or edit an "override" row, setting the relevant attribute to the provided value (or null)

3) Read-only rows:
- update the value of the attribute in the current row, but don't change anything else about the row (status, edited date, etc)

----

# Conceptual approach

SilvertreePIM is a tool for managing product and related content, where AI transformations are the principle way in which content will be updated and managed, and where the code is as lean as possible and customisation is done by code edits (using AI) rather than an over-engineered platform to allow all sorts of config-driven customisation.

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

Entities are related to each other with relationships of various types, e.g., "belongs to", "related to", etc.

## Pipelines

These are AI or automation pipelines that take a set of attributes, from the current entity or related entities, and produce an updated value of an attribute for an entity. Pipelines run when source attribute values change. 

Associated with pipelines are "evals", which are example outputs, used for refining AI prompts and ensuring that outputs don't change over time, similar in concept to unit or acceptance tests.

Pipelines also track stats like token usage, etc.

## Syncs

We sync attributes for products (and other entities) to Magento. In general, this system is the master for attribute values, but Magento is the master for attributes which are not included in this system (e.g., inventory data), and we can sync starting attribute values from Magento into SPIM.

## Attributes

There are several types of attributes, with different schemas and concepts for each attribute type, as follows. Entities are a combination of the attributes of each type.

A master attributes table stores all requried information on all attribute types, uniquely identified by entity_type_id and attribute name. Properties include:
- data_type: One of integer, text, html, json, select, multiselect, belongs_to, belongs_to_multi
- attribute_type: One of "versioned", "input", "timeseries"
- require_approval: whether automated updates to an attribute need approval before going live
- allowed_values: for select and multiselect attributes, available options
- linked_entity_type: for belongs_to fields, the name of the entity_type that the field refers to, and the field is the entity ID (or comma-separated list of IDs for multi fields)
- is_synced: whether the attribute is synced to external systems. Depending on attribute_type, this will be a read or write sync. Sync works using matching attribute names, and the sync logic handles matching data types.
- ui_class: if specified, a name of a class within a namespace, that handles displaying the attribute -- see below.

The ui_class object is a pluggable interface for:
- displaying the attribute value in a table (ie summarised)
- in a detail view page (full value, readonly). For example, this will also show the "justification" field for the attribute value, in a small font and grey writing, under the value.
- editing interface for the attribute.
- handling the data from the editing UI and turning back into the correct value format.
If not specific, falls back on a default class that renders the value sanely. Useful for showing, e.g., category paths and names instead of just IDs, etc.


## Versioned attributes

EAV-type structure. For sanity, we do NOT use a typed database field for attribute values, rather just a text field for values that can hold numbers, text, JSON, etc. Each attribute value has a number of fields:
- value_current: latest value, not necessarily approved
- value_approved: value approved for sync (but not yet synced)
- value_live: the value of the field as synced to external systems
- value_override: if not null, the value of the field forced by human override. The "current" value is still tracked separately as the value that would be used if not for the override.
- updated_at: timestamp
- input_hash: hash of input data used to calculate the field values by pipeline
- justification: AI-generated short phrase explaining the logic of the current value

Updates to this attribute obviously affect one or more of the value_... fields. Reads of the attribute value will in general return the value_current value, unless specifically requesting the override value.

## Input attributes

These are attributes pulled from other sources (e.g., sycned from external systems, or scraped). They are not versioned or produced by pipelines. This EAV table contains just the current value, and an updated_at field.

## Timeseries attributes

This EAV table has multiple values per attribute-entity pair, with a timestamp column to record timestamps. Used for, e.g., price and stock history for scraped products.

## Tech architecture
	•	Core app: Laravel (PHP) + Filament admin for back‑office UI and API.
	•	Worker(s) (later phases): Python service for scraping (Scrapy) and AI pipeline execution (FastAPI/Celery optional).
	•	Storage: MySQL
	•	Integration: Magento 2 via REST; thin Magento module only if/where APIs fall short.
	•	Secrets: env vars for now (12‑factor); revisit KMS/SM when needed.


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
4.2 Click to review and approvec changes, and bulk approval tickboxes.

## Phase 5: Sync to/from Magento
4.1 Sync from Magento for input attributes
4.2 Sync to Magento for versioned attributes. Sync code needs to handle mapping between our and their data_types, eg we might store something as text, and Magento as a select, in which case we need to add attribute option values.

(later phases: pipelines, relationships, etc)


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
    - Pipelines
- Settings
    - Syncs


⸻

# Data model

4.1 Core catalogue

attributes
	•	id PK
    •	product_type_id FK
	•	code (unique per product type)
	•	name, section_id, display_order
	•	data_type (string|number|bool|json|category|category_list|select|multiselect)
	•	behaviour ("readonly", "editable", "automated")
    •	review_required (always|low_confidence|never)
	•	external_source_id (nullable FK → external_platform)
	•	source_attributes (json array of attribute_ids) — lightweight graph reference
	•	pipeline_version (int) — the current version used to produce values
	•	pipeline_updated_at (ts)

attribute_sections
	•	id PK, name, display_order
    •	product_type_id FK

external_platform
	•	id PK, type (e.g., magento2), name
	•	config_json (non‑secret config; secrets live in env)

product_types
	•	id
	•	name
	•	description
    •	external_platform_id FK to which this produt type is synced, nullable
    •	external_platform_config json eg attribute set for magento

products
	•	id PK
    •	sku (unique per product_type)

products_live etc
	•	id FK
    •	(various attributes, none to start)
	•	status_id
	•	overridden (bool), value_override (string/json), override_at (ts)
	•	confidence (float 0..1), justification (text)
	•	minimal lineage: pipeline_version (copied from attribute at time of production), input_hash (hash of source inputs), produced_at (ts)
	•	updated_at, created_at

product_statuses
	•	id
	•	name. Seeder for values: "synced", "pending_review", "approved", "stale", "override"
    •	priority (int). Highest priority is viewed as the "current" value.
    •	readonly (bool). Some statuses (eg stale) no longer allow values to be edited

product_links
	•	id PK
	•	from_product_id FK
	•	to_product_id FK
    •	product_link_type_id

product_link_types
	•	id PK
    •	name, seeders for "is", "confirmed_match", "likely_match", "not_match", "related", "upsell", "crosssell"
	•	description

categories
	•	id PK, name, description, parent_category_id, category_tree_id
	•	category_path (int[]), category_breadcrumbs (text)

category_trees
	•	id PK, name, description

3.2 Scraping & matching

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

3.3 Evals (minimal)
	•	Keep attribute_evals only for prompt debugging if you want; no golden sets/gating.

3.4 Pipeline bookkeeping

pipelines
	•	id PK

pipeline_steps (if you want granular editing)
	•	id, step_order, type (ai|javascript), transform (prompt/code), output_schema (json schema)
	•	Versioning is coarse: attributes.pipeline_version increments when you “publish” pipeline changes.

pipeline_runs (for visibility/cost)
	•	id PK, attribute_id, started_at, ended_at, status, num_items, model_name, tokens_input, tokens_output, cost_estimate
