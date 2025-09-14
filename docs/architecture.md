SilverteePIM — Architecture & Intention

⸻

1) Project posture & goals
	•	Primary goal: materially improve product info quality and speed of publication for ~8–10k SKUs.
	•	Audience: internal merchandisers + ops; small team, high iteration rate.
	•	Tenancy: single‑tenant per brand/installation (separate DB). No hard multi‑tenant features.
	•	Orchestration: batch/cron‑centric; queues for work; no event bus required.
	•	Complexity posture: prefer simpler DB + code; avoid heavy idempotency, mapping versioning, or audit logs unless value‑add is clear.

2) Architecture

2.1 Products:
Products are a key quantity. We manage them with a `products` table, but there are different types of products, related to each other. Rather than a super complex EAV, we have various products_... tables for the different product types, and these have the relevant attributes as columns in that table.

Product types include:
1) live: live products on our ecomm platform, where the attributes are a subset of the attributes that Magento provides. These attributes are then synced to and from Magento, when edit and approved. Can have multiple product types here corresponding to different Magento attribute sets
2) scraped: products scraped from websites, with just basic fields like brand, category, price, stock, description, etc. We also have a second table products_scraped_data which is a log of price and stock data over time
3) supplier: product data provided by suppliers via a supplier portal, that will be manipulated into the ecomm attributes

Products are linked to each other with a product_links table, where links can be of various types, e.g., same product, related product, possible same product, etc

Each product_... row has a status, mapped in the product_statuses table. These include "synced", "pending_review", "override" (for human overrides of automated attributes), as well as a timestamp, so we can record different versions during approval flows. "override" will have values only for attributes that are actually overrides. Otherwise the highest priority status is assumed to be to active row

2.2 Product attributes:
The attributes table stores supporting information on product attributes, including type, description, source, syncing info for mapping to external platforms (eg magento attribute name).

When creating/deleting attributes, we add/remove fields from the relevant products_... table.

Attributes can be one of:
- readonly: pulled from external systems, not editable locally
- editable: expected to be edited in this system, and written to remote systems
- automated: produced by an automation pipeline (AI or code) based on other attributes of this or linked products.

2.3 Syncs:
We can import products from Magento, where we pull in all matching attributes for some or all Magento products

We can sync to Magento, which will:
- compare our last known "synced" attribute values, with what is available in Magento
- on differences, for editable or automated attributes, sync those back to our version with status "override". For readonly attributes, just overwrite our values
- send our values to Magento.

2.4 Scrapes:
For future work

2.5 Automation pipelines:
For future work

2.6 Categories
- multiple category trees per product type, e.g., one for traditional categories, one for supplier->brand->range
- multi- and single-select category attributes on products

2.6 UI structure (partial)
Menu:
- Dashboard
- Review
- Products
    - (one per product type)
- Attributes
    - Product types
    - Attributes (with a selector to choose product type)
    - Pipelines
- Settings
    - Syncs

Key pages:
- Products: 
    - Have a main table, with configurable columns displayed, and ideally remember these in the user session.
    - filters for each of the category trees, as a dropdown showing number of products next to each category level
    - quick filters for other attribues
    - click / edit a product to open the product form. Have this as a slide out (or similar idea) from the right, so that the main product list is still a little visible, and it's quick to click between items in the list without closing, reopening the edit window each time
    - product form is generated from attributes, in a form display. Next to attribute name put an icon for each of the attribute types. If there are multiple different values for the product with different statuses other than stale, have a little text with the current highest-priorty status, and a little dropdown arrow to change the version to show.  

⸻

3) Tech architecture
	•	Core app: Laravel (PHP) + Filament admin for back‑office UI and API.
	•	Worker(s): Python service for scraping (Scrapy) and AI pipeline execution (FastAPI/Celery optional).
	•	Storage: PostgreSQL (catalog + scraping), S3‑compatible object store for images if needed.
	•	Queues: Redis + Horizon for monitoring.
	•	Integration: Magento 2 via REST; thin Magento module only if/where APIs fall short.
	•	Secrets: env vars for now (12‑factor); revisit KMS/SM when needed.

⸻

4) Data model

4.1 Core catalogue

attributes
	•	id PK
    •	product_type_id FK
	•	code (unique per product type)
	•	name, section_id, display_order
	•	type (string|number|bool|json|category|category_list|select|multiselect)
	•	type ("readonly", "editable", "automated")
    •	review_required (always|low_confidence|never)
	•	external_source_id (nullable FK → external_platform)
	•	source_attributes (json array of attribute_ids) — lightweight graph reference
	•	pipeline_version (int) — the current version used to produce values
	•	pipeline_updated_at (ts)

attribute_sections
	•	id PK, name, display_order

external_platform
	•	id PK, type (e.g., magento2), name
	•	config_json (non‑secret config; secrets live in env)

product_types
	•	id
	•	name
	•	description

products
	•	id PK (internal UUID or serial), sku

products_live etc
	•	id FK
    •	(various attributes, none to start)
	•	status_id
	•	status (stale|pending_review|approved|queued_for_sync|syncing|synced) — current state only
	•	overridden (bool), value_override (string/json), override_at (ts)
	•	confidence (float 0..1), justification (text)
	•	minimal lineage: pipeline_version (copied from attribute at time of production), input_hash (hash of source inputs), produced_at (ts)
	•	updated_at, created_at

product_statuses
	•	id
	•	name. Seeder for values: "synced", "pending_review", "approved", "stale", "override"
    •	priority (int). Highest priority is viewed as the "current" value
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

⸻

4) Pipelines — execution & determinism (minimal)
	•	Deterministic runs: a value is a function of (attribute.pipeline_version, input_hash).
	•	Pipeline version contract: when you publish pipeline changes, bump attributes.pipeline_version. That version freezes prompt, model, and code used.
	•	Input hash: hash source attributes’ current values (and any observation payloads). Store on the values row so recomputation is idempotent.
	•	Confidence & justification: always stored on the value.
	•	Review policy: evaluate review_required at produce‑time:
        •	always ⇒ pending_review
        •	low_confidence ⇒ compare confidence to per‑attribute threshold
        •	never ⇒ auto‑approved
	•	State machine: enforced in code; only status column reflects current state. No history log.

Recompute triggers
	1.	Any source attribute value changed (compare updated_at) or input_hash differs.
	2.	Attribute pipeline_version incremented.
	3.	Manual “recompute” action from UI.

⸻

6) Scraping & matching
	•	Scraper: Scrapy (with Playwright when needed). Schedule by scrape_sources.scrape_cron.
	•	Quality checks: basic counters per run (pages fetched, products parsed, error rate). Alert if sharp drop vs last run.
	•	Matching: brand normalisation → candidate gen (GTIN exact, else title/pack‑size fuzzy) → AI/ML classifier. Write to scrape_product_matches and surface to a review queue.

⸻

7) Security, RBAC, compliance
	•	RBAC roles: Admin (configure attributes/pipelines/platforms), Editor (edit values/overrides), Reviewer (approve/decline), Ops (scraping/exports).
	•	Secrets: env variables only; keep platform API secrets out of DB.
	•	Compliance: scraping posture managed outside product scope; prefer public feeds/APIs when available.

⸻

8) Observability & dashboards (thin v1)
	•	Dashboard:
        •	Products by sync status; exceptions (failed exports).
        •	Scrape recency & volumetrics; simple trend vs previous run.
        •	Pipeline runs: items processed, avg latency, token/cost summaries by model.
	•	Logging: application logs with job correlation IDs; no external APM required initially.

⸻

10) Technology choices
	•	Laravel 12 (API, queues), Filament v3 (admin UI), Redis + Horizon (queues/monitoring), PostgreSQL 16.
	•	Python: Scrapy for crawling; simple FastAPI/Celery worker for pipeline execution (optional if you prefer plain scripts + queue).
	•	Front‑end: Filament components; reserve Inertia/React only for a future complex visual (e.g., advanced diff/pipeline graph) if needed.

⸻

11) Staged approach (execution plan)

Stage 1 — Project setup
	•	Laravel repo bootstrapped; Docker Compose; .env templates; environments (local/stage/prod).
	•	Tooling: PHPStan, Pint, PHPUnit, Infection (optional); CI (GitHub Actions) with unit + feature tests; basic PR checks.
	•	Horizon installed; Redis set up; healthcheck endpoint.

Stage 2 — Database schema
	•	Migrate core tables: products, attributes, attribute_sections, values (typed cols + status + minimal lineage), categories, category_trees, product types.
	•	Seeders for demo data. Repositories/services for values read/write with the state machine in code.

Stage 3 — Product browser UI
	•	Filament resources: Products (list/detail), Attributes (list/detail), basic Dashboard cards.
    •	Product types, attributes
	•	Product detail shows sections, value + justification + confidence, override toggle.

Stage 4 — Magento sync (to/from)
	•	Configure platform (base URL, tokens via env). Implement push of approved values; simple retry/backoff.
	•	Basic reconciliation fetch (nightly): detect downstream edits ⇒ mark overrides.
	•	Attribute mapping stored in attribute_exports.mapping_json (no versioning).
	•	Review queue: table with bulk approve; filter by confidence and section.

Stage 5 — Pipelines (deterministic minimal)
	•	Implement pipeline executor (Python worker or PHP if simpler for v1) reading source attributes, computing output, setting confidence, justification, input_hash, pipeline_version.
	•	Recompute triggers (source change, pipeline version bump, manual recompute).
	•	pipeline_runs table + Dashboard card for run stats and token/cost estimates.

Stage 6 — Scraping & matching
	•	Scrapy one competitor site; store in scrape_products/scrape_product_data.
	•	Brand/category normalisation tables + simple UI; product matching pipeline feeding scrape_product_matches.
	•	Matching review queue; action to confirm/deny link to internal products.

Stage 7 — Pipelines over scraped info
	•	Extend pipelines to read from observations (or directly from scrape_* if you skip observations) to enrich/normalise attributes (e.g., units, pack size).
	•	Route outputs into standard values flow (status, review policy, sync where relevant).

⸻

12) Risks consciously accepted (and escape hatches)
	•	No idempotency keys / export history: acceptable for internal use; add export_jobs later if retries cause issues.
	•	No value history/audit: acceptable now; add values_history append‑only later if needed.
	•	Single pipeline version per attribute: keeps things simple; if parallel versions become useful, evolve to explicit versions per value.
	•	Units as pipeline logic: faster now; if rules proliferate, introduce lightweight attribute_constraints later.

⸻

13) Backend components

1. Scraping infrastructure, using scrapy most likely to do the scraping
    1. Scraping
    2. Quality control on scraping, eg spotting drop in number of products
    3. Matching, using AI: match brands, categories, then match products within brands
2. Attribute pipelines. On a cron or manually triggered
    1. Detect attribute values that have an updated_at before the latest of the updated_ats of source attribute values, OR where a pipeline step has been updated more recently
    2. Run the pipeline, updating appropriately
3. Sync to external platforms, when reviewed
    1. Sync all “for sync” attribute values to the external platform

----

14) Other conventions

	•	Conventions
        •	Timestamps everywhere (created_at, updated_at).
        •	Use Postgres JSON (maps to JSONB) and create GIN index where JSON queries matter.
	•	Status machine (code-level)
        •	Allowed transitions:
    stale → pending_review → approved → queued_for_sync → syncing → synced
    With shortcuts: stale → approved (if review_required=never) and approved ↔ pending_review (if new info arrives).
        •	A helper service (e.g., ValueStateService) enforces transitions.
	•	Review policy
        •	always → set pending_review.
        •	low_confidence → compare confidence against attribute-level threshold (default, say, 0.8).
        •	never → set approved.
	•	Determinism
        •	On compute: copy attributes.pipeline_version into the values.pipeline_version, compute an input_hash from source inputs; set produced_at=now().
	•	Initial env
        •	DB_CONNECTION=pgsql with your container host db
        •	QUEUE_CONNECTION=redis
        •	APP_URL=http://spim.test:8080
