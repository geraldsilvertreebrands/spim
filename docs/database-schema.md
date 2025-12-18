# Database Schema Documentation

> Generated: 2025-12-13
> Total Tables: 27 + 2 Views = 29 objects

## Overview

The Silvertree PIM database uses an Entity-Attribute-Value (EAV) architecture to support flexible product and category data modeling with versioning, approval workflows, and AI pipeline integration.

## Entity Relationship Diagram

```mermaid
erDiagram
    %% Core Entity System
    entity_types ||--o{ entities : "has many"
    entity_types ||--o{ attributes : "has many"
    entity_types ||--o{ attribute_sections : "has many"
    entity_types ||--o{ pipelines : "has many"
    entity_types ||--o{ sync_runs : "tracks"

    %% Attributes relationships
    attributes ||--o| entity_types : "linked_entity_type"
    attributes ||--o| pipelines : "driven by"
    attributes ||--o{ eav_versioned : "stores values"
    attributes ||--o{ eav_input : "stores values"
    attributes ||--o{ eav_timeseries : "stores values"
    attributes ||--o{ entity_attr_links : "stores links"
    attributes ||--o| attribute_sections : "belongs to"

    %% Entities EAV relationships
    entities ||--o{ eav_versioned : "has values"
    entities ||--o{ eav_input : "has values"
    entities ||--o{ eav_timeseries : "has values"
    entities ||--o{ entity_attr_links : "has links"
    entities ||--o{ sync_results : "tracked in"
    entities ||--o{ pipeline_evals : "evaluated by"

    %% Pipeline relationships
    pipelines ||--o{ pipeline_modules : "has steps"
    pipelines ||--o{ pipeline_runs : "execution history"
    pipelines ||--o{ pipeline_evals : "test cases"

    %% Sync relationships
    sync_runs ||--o{ sync_results : "has results"
    sync_runs }o--|| users : "triggered by"
    sync_results }o--o| attributes : "for attribute"

    %% User relationships
    users ||--o{ user_preferences : "has preferences"
    users ||--o{ model_has_roles : "has roles"
    users ||--o{ model_has_permissions : "has permissions"

    %% Permission relationships
    roles ||--o{ model_has_roles : "assigned to"
    roles ||--o{ role_has_permissions : "has permissions"
    permissions ||--o{ model_has_permissions : "assigned to"
    permissions ||--o{ role_has_permissions : "granted to"

    %% Table definitions
    entity_types {
        bigint id PK
        string name UK
        string display_name
        string description
        timestamps
    }

    entities {
        ulid id PK
        bigint entity_type_id FK
        string entity_id UK
        timestamps
    }

    attributes {
        bigint id PK
        bigint entity_type_id FK
        string name
        enum data_type
        enum attribute_type
        enum review_required
        json allowed_values
        bigint linked_entity_type_id FK
        bigint section_id FK
        int sort_order
        string display_name
        boolean is_readonly
        boolean is_overridable
        enum is_sync
        string magento_attribute_code
        ulid pipeline_id FK
        boolean is_synced
        string ui_class
        timestamps
    }

    attribute_sections {
        bigint id PK
        bigint entity_type_id FK
        string name
        int sort_order
        timestamps
    }

    eav_versioned {
        bigint id PK
        ulid entity_id FK
        bigint attribute_id FK
        longtext value_current
        longtext value_approved
        longtext value_live
        longtext value_override
        string input_hash
        int pipeline_version
        string justification
        decimal confidence
        json meta
        timestamps
    }

    eav_input {
        bigint id PK
        ulid entity_id FK
        bigint attribute_id FK
        longtext value
        string source
        timestamps
    }

    eav_timeseries {
        bigint id PK
        ulid entity_id FK
        bigint attribute_id FK
        timestamp observed_at
        longtext value
        string source
        json meta
        timestamps
    }

    entity_attr_links {
        bigint id PK
        ulid entity_id FK
        bigint attribute_id FK
        ulid target_entity_id FK
        timestamps
    }

    pipelines {
        ulid id PK
        bigint attribute_id FK UK
        bigint entity_type_id FK
        string name
        int pipeline_version
        timestamp pipeline_updated_at
        json entity_filter
        int max_entities
        timestamp last_run_at
        enum last_run_status
        int last_run_duration_ms
        int last_run_processed
        int last_run_failed
        bigint last_run_tokens_in
        bigint last_run_tokens_out
        timestamps
    }

    pipeline_modules {
        ulid id PK
        ulid pipeline_id FK
        smallint order
        string module_class
        json settings
        timestamps
    }

    pipeline_runs {
        ulid id PK
        ulid pipeline_id FK
        int pipeline_version
        enum triggered_by
        string trigger_reference
        enum status
        int batch_size
        int entities_processed
        int entities_failed
        int entities_skipped
        bigint tokens_in
        bigint tokens_out
        timestamp started_at
        timestamp completed_at
        text error_message
        timestamps
    }

    pipeline_evals {
        ulid id PK
        ulid pipeline_id FK
        ulid entity_id FK
        string input_hash
        json desired_output
        text notes
        json actual_output
        text justification
        decimal confidence
        timestamp last_ran_at
        timestamps
    }

    sync_runs {
        bigint id PK
        bigint entity_type_id FK
        enum sync_type
        timestamp started_at
        timestamp completed_at
        enum status
        int total_items
        int successful_items
        int failed_items
        int skipped_items
        text error_summary
        string triggered_by
        bigint user_id FK
        timestamps
    }

    sync_results {
        bigint id PK
        bigint sync_run_id FK
        ulid entity_id FK
        bigint attribute_id FK
        string item_identifier
        enum operation
        enum status
        text error_message
        json details
        timestamp created_at
    }

    users {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at
        string password
        boolean is_active
        string remember_token
        timestamps
    }

    user_preferences {
        bigint id PK
        bigint user_id FK
        string key
        json value
        timestamps
    }

    roles {
        bigint id PK
        string name
        string guard_name
        timestamps
    }

    permissions {
        bigint id PK
        string name
        string guard_name
        timestamps
    }
```

## Table Groups

### 1. Core Laravel Tables (6 tables)
| Table | Description | Owner |
|-------|-------------|-------|
| `users` | Application users with authentication | Laravel |
| `password_reset_tokens` | Password reset token storage | Laravel |
| `sessions` | Session storage | Laravel |
| `cache` | Application cache | Laravel |
| `cache_locks` | Cache lock management | Laravel |
| `migrations` | Migration tracking | Laravel |

### 2. Queue Tables (3 tables)
| Table | Description | Owner |
|-------|-------------|-------|
| `jobs` | Queued jobs | Laravel |
| `job_batches` | Job batch tracking | Laravel |
| `failed_jobs` | Failed job records | Laravel |

### 3. Permission Tables (5 tables - Spatie)
| Table | Description | Owner |
|-------|-------------|-------|
| `roles` | Role definitions | Spatie |
| `permissions` | Permission definitions | Spatie |
| `model_has_roles` | User-Role pivot | Spatie |
| `model_has_permissions` | User-Permission pivot | Spatie |
| `role_has_permissions` | Role-Permission pivot | Spatie |

### 4. Entity System Tables (5 tables)
| Table | Description | Owner |
|-------|-------------|-------|
| `entity_types` | Types: Product, Category | PIM Core |
| `entities` | Individual records (products, categories) | PIM Core |
| `attributes` | Field definitions per entity type | PIM Core |
| `attribute_sections` | UI groupings for attributes | PIM Core |
| `user_preferences` | Per-user UI settings | PIM Core |

### 5. EAV Tables (4 tables + 2 views)
| Table | Description | Owner |
|-------|-------------|-------|
| `eav_versioned` | Versioned values (current/approved/live/override) | PIM EAV |
| `eav_input` | Input/source values | PIM EAV |
| `eav_timeseries` | Time-series data | PIM EAV |
| `entity_attr_links` | Entity relationships (belongs_to) | PIM EAV |
| `entity_attr_json` (VIEW) | Aggregated JSON bags | PIM EAV |
| `entity_attribute_resolved` (VIEW) | Resolved values view | PIM EAV |

### 6. Sync Tables (2 tables)
| Table | Description | Owner |
|-------|-------------|-------|
| `sync_runs` | Sync execution records | Magento Sync |
| `sync_results` | Per-item sync results | Magento Sync |

### 7. Pipeline Tables (4 tables)
| Table | Description | Owner |
|-------|-------------|-------|
| `pipelines` | AI pipeline definitions | AI Pipeline |
| `pipeline_modules` | Steps in pipelines | AI Pipeline |
| `pipeline_runs` | Execution history | AI Pipeline |
| `pipeline_evals` | Test cases/evaluations | AI Pipeline |

## Key Relationships

### Entity System
```
entity_types (1) ──────────────────── (*) entities
     │                                      │
     │                                      │
     └──── (*) attributes ─────────────────┼──── (*) eav_versioned
                  │                        │            │
                  │                        └──── (*) eav_input
                  │                        │
                  └── (1) attribute_sections└──── (*) eav_timeseries
```

### Pipeline System
```
attributes (1) ──── (1) pipelines ──── (*) pipeline_modules
                         │
                         ├──── (*) pipeline_runs
                         │
                         └──── (*) pipeline_evals ──── (1) entities
```

### Sync System
```
entity_types (1) ──── (*) sync_runs ──── (*) sync_results
                           │                    │
                           │                    └──── (0..1) entities
                           │                    │
                           └──── (0..1) users   └──── (0..1) attributes
```

## Primary Keys

| Type | Tables |
|------|--------|
| `bigint AUTO_INCREMENT` | Most tables (users, attributes, roles, etc.) |
| `ULID` | entities, pipelines, pipeline_modules, pipeline_runs, pipeline_evals |

## Enums

### attributes.data_type
- `integer`, `text`, `html`, `json`, `select`, `multiselect`, `belongs_to`, `belongs_to_multi`

### attributes.attribute_type
- `versioned`, `input`, `timeseries`

### attributes.review_required
- `always`, `low_confidence`, `no`

### attributes.is_sync
- `no`, `from_external`, `to_external`, `bidirectional`

### sync_runs.sync_type
- `options`, `products`, `full`

### sync_runs.status / pipeline_runs.status
- `running`, `completed`, `failed`, `partial` (sync) / `aborted` (pipeline)

### sync_results.operation
- `create`, `update`, `skip`, `validate`, `conflict`

### pipeline_runs.triggered_by
- `schedule`, `entity_save`, `manual`

## Foreign Key Summary

| From Table | Column | To Table | On Delete |
|------------|--------|----------|-----------|
| entities | entity_type_id | entity_types | CASCADE |
| attributes | entity_type_id | entity_types | CASCADE |
| attributes | linked_entity_type_id | entity_types | SET NULL |
| attributes | section_id | attribute_sections | SET NULL |
| attributes | pipeline_id | pipelines | SET NULL |
| attribute_sections | entity_type_id | entity_types | CASCADE |
| eav_versioned | entity_id | entities | CASCADE |
| eav_versioned | attribute_id | attributes | CASCADE |
| eav_input | entity_id | entities | CASCADE |
| eav_input | attribute_id | attributes | CASCADE |
| eav_timeseries | entity_id | entities | CASCADE |
| eav_timeseries | attribute_id | attributes | CASCADE |
| entity_attr_links | entity_id | entities | CASCADE |
| entity_attr_links | target_entity_id | entities | CASCADE |
| entity_attr_links | attribute_id | attributes | CASCADE |
| pipelines | attribute_id | attributes | RESTRICT |
| pipelines | entity_type_id | entity_types | RESTRICT |
| pipeline_modules | pipeline_id | pipelines | CASCADE |
| pipeline_runs | pipeline_id | pipelines | CASCADE |
| pipeline_evals | pipeline_id | pipelines | CASCADE |
| pipeline_evals | entity_id | entities | CASCADE |
| sync_runs | entity_type_id | entity_types | SET NULL |
| sync_runs | user_id | users | SET NULL |
| sync_results | sync_run_id | sync_runs | CASCADE |
| sync_results | entity_id | entities | SET NULL |
| sync_results | attribute_id | attributes | SET NULL |
| user_preferences | user_id | users | CASCADE |
| model_has_roles | role_id | roles | CASCADE |
| model_has_permissions | permission_id | permissions | CASCADE |
| role_has_permissions | role_id | roles | CASCADE |
| role_has_permissions | permission_id | permissions | CASCADE |

## Indexes

### Performance-Critical Indexes
- `entities`: `(entity_type_id, entity_id)` UNIQUE
- `attributes`: `(entity_type_id, name)` UNIQUE
- `eav_versioned`: `(entity_id, attribute_id)` UNIQUE
- `sync_runs`: `(entity_type_id, created_at)`
- `pipeline_runs`: `(pipeline_id, created_at)`, `(status, started_at)`

## Version History

| Date | Change |
|------|--------|
| 2025-10-01 | Initial entity system |
| 2025-10-09 | Added sync tracking tables |
| 2025-10-15 | Added pipeline tables |
| 2025-11-12 | Added bidirectional sync support |
