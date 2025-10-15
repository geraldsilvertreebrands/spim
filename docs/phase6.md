# Phase 6 — Pipelines Implementation

## Objectives
We will implement pipeline-driven attribute generation, including backend services, configuration UI, job orchestration, and eval workflows. Pipelines produce one attribute value per run and integrate tightly with the existing EAV, approval, and sync flows.

## Scope
- Database additions and migrations
- Module class hierarchy and contracts
- Execution services, batching, and queuing
- Filament admin UI for pipeline configuration and monitoring
- Eval lifecycle and nightly checks
- Testing and roll-out plan

## Database & Models
### Schema changes
- `pipelines`
  - `id` (PK ULID)
  - `attribute_id` (FK `attributes.id`, unique, 1:1)
  - `entity_type_id` (FK `entity_types.id`)
  - `name` (nullable string, optional friendly label)
  - `pipeline_version` (unsigned integer, default 1)
  - `pipeline_updated_at` (timestamp, default current)
  - `last_run_at`, `last_run_status`, `last_run_duration_ms` (nullable)
  - `last_run_processed`, `last_run_failed`, `last_run_tokens_in`, `last_run_tokens_out` (nullable integers)
  - `created_at`, `updated_at`

  *Constraints:*
  - `attribute_id` unique index.
  - `entity_type_id`+`name` unique if we start naming pipelines per entity type.
  - `attribute_id` FK updates cascade, delete restrict.

- `pipeline_modules`
  - `id` (PK ULID)
  - `pipeline_id` (FK `pipelines.id`)
  - `order` (unsigned smallint)
  - `module_class` (string FQCN)
  - `settings` (JSON)
  - `created_at`, `updated_at`

  *Constraints:*
  - Unique index on (`pipeline_id`, `order`).
  - FK delete cascade.

- `pipeline_runs`
  - `id` (PK ULID)
  - `pipeline_id` (FK)
  - `pipeline_version` (unsigned integer)
  - `triggered_by` (enum: `schedule`, `entity_save`, `manual`)
  - `trigger_reference` (nullable string, e.g. entity ID or user ID)
  - `status` (enum: `running`, `completed`, `failed`, `aborted`)
  - `batch_size`, `entities_processed`, `entities_failed`, `entities_skipped`
  - `tokens_in`, `tokens_out` (nullable integers)
  - `started_at`, `completed_at`
  - `error_message` (nullable text)
  - `created_at`

- `pipeline_evals`
  - `id` (PK ULID)
  - `pipeline_id` (FK)
  - `entity_id` (FK `entities.id`)
  - `input_hash` (string 64)
  - `desired_output` (JSON)
  - `notes` (nullable text)
  - `actual_output` (JSON, nullable)
  - `justification` (nullable text)
  - `confidence` (nullable decimal 5,4)
  - `last_ran_at` (nullable timestamp)
  - `created_at`, `updated_at`

  *Constraints:*
  - Unique index on (`pipeline_id`, `entity_id`).
  - Cascade on entity delete (or archive strategy TBD).

- `attributes`
  - Add `pipeline_id` nullable FK (unique), default null. Backfill existing rows after pipelines created.

- `eav_versioned`
  - Add `pipeline_version` (unsigned integer, nullable) so we can compare against `pipelines.pipeline_version`.

### Model updates
- `Pipeline` model with relations: `attribute`, `modules`, `runs`, `evals`.
- `PipelineModule` model with `settings` cast, `module()` helper returning module instance via registry.
- `PipelineRun` for history and stats.
- `PipelineEval` for eval records.

## Module Contract & Registry
Create `App\Pipelines\Contracts\PipelineModuleInterface`:

```php
interface PipelineModuleInterface
{
    public static function definition(): PipelineModuleDefinition;

    public static function form(Form $form): Form;

    public static function getInputAttributes(array $settings): Collection;

    public function __construct(PipelineModuleConfig $config);

    public function validateSettings(array $data): array;

    public function process(PipelineContext $context): PipelineResult;
}
```

Helper value objects:
- `PipelineModuleDefinition` (id/slug, label, description, type = `source|processor`).
- `PipelineModuleConfig` (module model, settings array, pipeline context helpers).

Base class `AbstractPipelineModule` implements boilerplate and leaves `process()` abstract. Modules register themselves via a service provider (e.g. tagging with `pipeline.module`). A `PipelineModuleRegistry` resolves modules and ensures first module is source.

Data classes:
- `PipelineContext`
  - `entityId`, `attributeId`, `inputs` (array), `batchIndex`, `pipelineVersion`, `meta` (array), `settings` (module-specific read-only view).
  - methods to access `input($key)`, `allInputs()`.
- `PipelineResult`
  - `value`, `confidence`, `justification`, `meta`, `status` (`ok|skipped|error`), `errors` (array).
  - helper `static ok(...)`, `static error(...)`.

## Execution Flow
Pipeline runs occur in three scenarios:
1. Nightly scheduler dispatches `RunPipelineBatch` jobs per pipeline (re-run evals afterwards).
2. Entity save listener dispatches `RunPipelineForEntity` for affected pipelines.
3. Manual trigger queues `RunPipelineBatch`.

### Job orchestration
- `RunPipelineBatch` queries entities needing work (based on `input_hash` diff or pipeline version change), chunks into batches (default 200, configurable). For each batch, it:
  - Resolves pipeline modules and Node helper if needed.
  - Builds shared `PipelineContextFactory` with inputs per entity.
  - Executes modules sequentially; batches pass through Node helper once per module invocation (batch payload).
  - Aborts entire run on first failed entity, marking run status `failed` and recording error.
  - Updates `eav_versioned` with new value, `input_hash`, `justification`, `confidence`, `pipeline_version` via existing `EavWriter`.
- `RunPipelineForEntity` handles single entity, same flow minus batching.

### Input discovery & dependency ordering
- On pipeline save, collect all source module `getInputAttributes()` results. Build adjacency list of attribute dependencies across pipelines.
- Run Kahn’s algorithm to derive execution order for nightly scheduler.
- Detect cycles and block save with validation error.

### Hashing strategy
- Compute stable JSON of source module outputs + module settings + pipeline version; hash with SHA-256.
- Store hash in `eav_versioned.input_hash`. Runs skip unchanged hashes unless forced (manual run or version bump).

### Node helper
- Add `resources/node/pipeline-runner.js` (or similar).
- Accept payload: `{ code, items: [{ entityId, inputs, contextMeta }] }`.
- Execute using Node `vm` with `timeout` and limited globals (`$json`, `$input`, helpers).
- Return `{ results: [...], logs, errors }`.
- PHP orchestrator wraps via `Symfony\Process`, handles timeouts, parse response, map errors.

## AI Prompt Processor
- Form: prompt textarea, JSON schema textarea with template dropdown (string, integer, object with justification/confidence, etc.).
- Validation ensures schema is valid JSON and includes required fields.
- Execution builds prompt lines (`"Name: Value"`), passes along schema, temperature 0, JSON output mode.
- Capture model, tokens in/out, and store in run stats.
- Surface warnings if output missing fields; fail pipeline when schema parse fails.

## Calculation Processor
- Form: code editor (monaco component in Filament) with sample context docs.
- Execution sends entire batch to Node helper. Enforce max runtime per batch (e.g. 10s) and memory limit.
- Defines `$json` (inputs array), `$value` (current value), `$meta`, returns object with `value`, optional `justification`, `confidence`.
- Handle thrown errors by aborting pipeline run.

## Eval Workflow
- UI: eval table in pipeline detail, edit desired output/notes inline, badge failing evals (`actual_output != desired_output`).
- CLI/Job: after each pipeline batch run, queue `RunPipelineEvals` to recompute evals (or same job if small).
- Nightly scheduler runs eval job for all pipelines regardless of hash changes.
- On entity override UI, “Add override as eval” posts to pipeline evals, pre-filling desired output from override.

## UI Implementation
- New Filament menu `Settings → Pipelines` table listing: entity type, attribute name, last run status/time, avg confidence (rolling), eval counts/failures.
- Detail page tabs/cards:
  - **Modules**: reorderable list, module picker, module-specific forms rendered via Livewire components provided by each module class.
  - **Evals**: table with inline editing, manual “Run evals” and “Run pipeline” buttons (dispatch jobs).
- Validation on save: ensure first module type = source, at least one processor, check DAG.
- Show last run log summary + link to `pipeline_runs` history.

## Testing Strategy
- Feature tests for pipeline creation, module CRUD, DAG validation.
- Unit tests for module registry, context/result classes, hashing.
- Integration tests for AI prompt module (mock OpenAI) and calculation module (mock Node helper).
- Queue job tests verifying batching, versioning, abort-on-failure, eval reruns.
- UI dusk/livewire tests for module forms and eval interactions.

## Roll-out Steps
1. Build migrations and models.
2. Implement module framework + base classes.
3. Deliver initial source/processor modules (Attributes, AI prompt, Calculation).
4. Ship Node helper, configure Docker image to include Node runtime.
5. Implement pipeline services and jobs.
6. Add Filament UI pages.
7. Seed demo pipeline for QA.
8. Write docs/playbook for creating new modules.
9. Monitor nightly job performance and adjust batch sizes/concurrency.

## Open Questions / Follow-ups
- How do we surface cost/token trends to business users (dashboards vs. exports)?
- Do we need a manual retry flow for partially failed runs once we introduce per-entity resilience?
- Should eval desired outputs support soft comparisons (e.g. tolerance for numeric deltas) in future?
