Phase 3 — Entity browsing
-------------------------

Goals
- Provide discoverable, efficient browsing of entities per type.
- Configurable listing columns using attribute renderers.
- Slide-over detail panel with fast navigation.

Deliverables
- Filament navigation: one menu per `EntityType` under Entities.
- Entity list screen per type with:
  - Search (by core columns and selected attributes)
  - Sort by selected attributes
  - Column chooser persisted per user
- Entity detail slide-over:
  - Left: attribute names with icons (type, synced, overridden)
  - Right: values rendered by ui_class
  - Actions: override value, clear override

Tasks
1) Routing & resources
  - Dynamic resource/controller keyed by `entity_type_id`
  - Policy gate for visibility

2) Listing implementation
  - Data source joins to `entity_attribute_resolved` for selected columns
  - Column components call ui_class summarise()
  - Filters for common attributes (brand, status, etc.) via scopes

3) Column chooser persistence
  - Store user preference per `entity_type_id` in a `user_preferences` table (json)
  - Default set from entity type config

4) Slide-over detail panel
  - Fetch per-entity JSON bag for fast rendering
  - ui_class show() renders value and metadata (justification, confidence)
  - Buttons:
    - Override → exposes ui_class form()
    - Save override → calls ui_class save() which uses writer
    - Clear override

5) Pagination & performance
  - Use server-side pagination; avoid loading JSON bag for list rows
  - Select only columns required for the visible attributes

6) Tests
  - Feature tests for listing, sorting, filtering, and detail slide-over

Acceptance criteria
- User can browse entities by type with configurable columns.
- Sorting and filtering by attributes works using scopes/joins.
- Detail slide-over renders values via ui_class and supports overrides.
- Preferences persist per user and type.

Open questions
- Do we expose bulk override/edit? Suggested: defer until after approval workflow. Answer: agree
- How to search across free-text attributes? Suggested: add a basic LIKE on chosen text attrs; plan full-text later. Answer: agree
Testing plan
- Seeds/fixtures: factories for entities with common attributes (brand, title, weight) and linked categories.
- Feature tests:
  - Listing renders selected columns and paginates.
  - Sorting by attribute works via scope join.
  - Filtering via `whereAttr` applies correctly.
  - Detail slide-over fetches JSON bag and shows override/current.
  - Override action persists and reflects immediately in detail view.
- Performance checks (lightweight): assert number of queries stays bounded for list and detail.
