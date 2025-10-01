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

These are AI or automation pipelines that take a set of attributes, from the current entity or related entities, and produce an updated value of an attribute for an entity. Pipelines run when source attribute values change. 

Associated with pipelines are "evals", which are example outputs, used for refining AI prompts and ensuring that outputs don't change over time, similar in concept to unit or acceptance tests.

Pipelines also track stats like token usage, etc.

## Syncs

We sync attributes for products (and other entities) to Magento. In general, this system is the master for attribute values, but Magento is the master for attributes which are not included in this system (e.g., inventory data), and we can sync starting attribute values from Magento into SPIM.

## Attributes

There are several types of attributes, with different schemas and concepts for each attribute type, as follows. Entities are a combination of the attributes of each type.

A master `attributes` table stores all required information on all attribute types, uniquely identified by `entity_type_id` and attribute `name`. Properties include:
- data_type: One of integer, text, html, json, select, multiselect, belongs_to, belongs_to_multi
- attribute_type: One of "versioned", "input", "timeseries"
- review_required: one of "always" | "low_confidence" | "no". When set to "always", automated updates require human approval. When set to "low_confidence", automated updates are auto-approved only if confidence ≥ 0.8. When set to "no", automated updates are auto-approved.
- allowed_values: for select and multiselect attributes, available options
- linked_entity_type: for belongs_to fields, the name of the `entity_type` that the field refers to (for multi, it refers to multiple). Storage uses a link table (`entity_attr_links`) rather than comma-separated IDs.
- is_synced: whether the attribute is synced to external systems. Depending on attribute_type, this will be a read or write sync. Sync works using matching attribute names, and the sync logic handles matching data types.
- ui_class: if specified, a name of a class within a namespace, that handles displaying the attribute -- see below.

The ui_class object is a pluggable interface for:
- displaying the attribute value in a table (ie summarised)
- in a detail view page (full value, readonly). For example, this will also show the "justification" field for the attribute value, in a small font and grey writing, under the value.
- editing interface for the attribute.
- handling the data from the editing UI and turning back into the correct value format.
If not specified, falls back on a default class that renders the value sanely. Useful for showing, e.g., category paths and names instead of just IDs, etc.


## Versioned attributes

EAV-type structure. For sanity, we do NOT use a typed database field for attribute values, rather just a text field for values that can hold numbers, text, JSON, etc. Each attribute value has a number of fields:
- value_current: latest value, not necessarily approved
- value_approved: value approved for sync (but not yet synced)
- value_live: the value of the field as synced to external systems
- value_override: if not null, the value of the field forced by human override. The "current" value is still tracked separately as the value that would be used if not for the override.
- updated_at: timestamp
- input_hash: hash of input data used to calculate the field values by pipeline
- justification: AI-generated short phrase explaining the logic of the current value
- confidence: score 0..1

Updates to this attribute affect one or more of the `value_*` fields. Reads of the attribute value will in general return the `value_current` value, unless specifically requesting the override value. If approvals are required and `value_current` changes, we do not clear `value_approved` so it remains comparable and easy to restore.

## Input attributes

These are attributes pulled from other sources (e.g., synced from external systems, or scraped). They are not versioned or produced by pipelines. This EAV table contains just the current value, and an updated_at field.

## Timeseries attributes

This EAV table has multiple values per attribute-entity pair, with a timestamp column to record timestamps. Used for, e.g., price and stock history for scraped products.

## Resolved values and JSON bags

To avoid dynamic SQL pivots, we use MySQL views to unify values from the three EAV buckets and pre-aggregate them into per-entity JSON objects:
- `eav_timeseries_latest`: latest datapoint per `(entity_id, attribute_id)` using a window function.
- `entity_attribute_resolved`: unified rowset from `versioned`, `input`, and latest `timeseries`.
- `entity_attr_json`: aggregates resolved values into two JSON bags per entity (with overrides applied vs current-only) for quick reads.

Relations (`belongs_to`, `belongs_to_multi`) are stored in `entity_attr_links` and can be exposed via a companion aggregation view when needed.

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
4.2 Click to review and approve changes, and bulk approval tickboxes.

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

# Data model - OLD, NEEDS REVISION!!!

4.1 Core catalogue

attributes
	•	id PK
    •	product_type_id FK
	•	name (unique per product type)
	•	display_name, section_id, display_order
	•	data_type
    •	attribute_type (versioned|input|timeseries)
    •	review_required (always|low_confidence|no)
	•	external_source_id (nullable FK → external_platform)
	
attribute_sections
	•	id PK, name, display_order
    •	product_type_id FK

external_platform
	•	id PK, type (e.g., magento2), name
	•	config_json (non‑secret config; secrets live in env)

entity_types
	•	id
	•	name
	•	description
    •	external_platform_id FK to which this product type is synced, nullable
    •	external_platform_config json eg attribute set for magento

entities
	•	id PK
    •	entity_id (natural ID from external system, unique per entity_type)
    •	entity_type

values
    •	id PK
    •	attribute_id
    •	entity_id
    •	value_current
    •	value_approved
    •	value_live
    •	value_override
    •	input_hash
    •	justification
    •	confidence

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
