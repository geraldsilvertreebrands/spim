Phase 2 — UI and infrastructure for attributes
---------------------------------------------

Goals
- Implement attribute CRUD and configuration.
- Provide typed read/write access to attributes via model shims and writer.
- Introduce UI classes for rendering/editing attributes.
- Ensure initial Filament UI integration is usable for admins.

Deliverables
- Models:
  - `Attribute` (backed by `attributes` table)
  - `EntityType` (backed by `entity_types`)
- Admin UI (Filament):
  - Manage `EntityType` (create/edit/delete)
  - Manage `Attribute` (scoped to `EntityType`)
  - Basic entity browser placeholder (Phase 3 will flesh out listing)
- Infrastructure:
  - Attribute caster registry (`App\Support\AttributeCaster`) [done]
  - EAV writer (`App\Services\EavWriter`) [done]
  - Entity model shims for getters/setters and scopes [done]
  - UI class interface + default implementations
- Validation:
  - Enforce `allowed_values` for select/multiselect
  - Enforce `review_required` semantics on write (confidence)
  - Belongs-to target type validation

Tasks
1) Eloquent models and factories
  - `app/Models/EntityType.php`: name, description; hasMany attributes; hasMany entities
  - `app/Models/Attribute.php`: belongsTo entityType; optional belongsTo linkedEntityType
  - Policies (optional now) for admin access via Filament

2) Attribute service layer
  - `App\Services\AttributeService`:
    - `findByName(entityTypeId, name)`
    - `validateValue(attribute, value)` — uses `allowed_values` and type checks
    - `coerceIn/Out` — proxy to `AttributeCaster` and relationships

3) UI class contract and defaults
  - Create interface `App\Contracts\AttributeUi` with methods:
    - `summarise($entity, $attribute)` → string|Htmlable
    - `show($entity, $attribute)` → string|Htmlable
    - `form($entity, $attribute)` → Filament/Laravel form component definition
    - `save($entity, $attribute, $input)` → persist using writer/service
  - Provide defaults:
    - `TextUi`, `HtmlUi`, `IntegerUi`, `JsonUi`, `SelectUi`, `MultiselectUi`, `BelongsToUi`, `BelongsToMultiUi`
  - Register lookup in a small registry keyed by `ui_class` or `data_type`

4) Filament resource: Entity Types
  - Resource with fields: name, description
  - List, create, edit, delete

5) Filament resource: Attributes
  - Fields:
    - entity_type_id (select)
    - name (unique per type)
    - data_type (enum)
    - attribute_type (enum)
    - review_required (enum: always|low_confidence|no)
    - allowed_values (array/json; visible for select/multiselect)
    - linked_entity_type_id (visible for belongs_to/_multi)
    - is_synced (bool)
    - ui_class (string, optional)
  - List with filters by entity type and data_type
  - Validation:
    - unique (entity_type_id, name)
    - allowed_values required for select/multiselect

6) API endpoints (optional if UI-only)
  - GET entity types, GET/POST attributes (scoped by entity type)
  - Simple auth middleware

7) Tests
  - Unit tests for AttributeService validation and coercion
  - Feature tests for Attribute CRUD and writer interaction

Acceptance criteria
- Admin can create/edit/delete entity types and attributes.
- Attributes enforce typing and allowed values when written.
- Entity model can read/write attributes via standard getters/setters.
- UI classes render basic summary and edit forms for default types.

Open questions
- Do we allow renaming attribute `name` once created? Suggested: allow but migrate data with a safe task.
- For `allowed_values`, store labels vs keys? Suggested: store keys, UI maps labels.
- What’s the canonical `entity_id` format per type (SKU vs numeric)? Define per `entity_types` description.

Risks / follow-ups
- UI classes will grow in complexity; keep defaults minimal and composable.
- Validation drift between UI and service — ensure single source of truth in `AttributeService`.
