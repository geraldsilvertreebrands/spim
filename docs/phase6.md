# Phase 6 — Pipelines Implementation

**Status**: ✅ **COMPLETE** (October 15, 2025)

## Objectives
We will implement pipeline-driven attribute generation, including backend services, configuration UI, job orchestration, and eval workflows. Pipelines produce one attribute value per run and integrate tightly with the existing EAV, approval, and sync flows.

## Implementation Status
All core functionality and UI complete:
- ✅ Database schema and migrations
- ✅ Module class hierarchy and contracts
- ✅ Execution services, batching, and queuing
- ✅ Full Filament admin UI (create, edit, module builder, eval management)
- ✅ Eval lifecycle and nightly scheduler
- ✅ Comprehensive testing (44 tests, 114 assertions)
- ✅ Documentation (architecture, implementation, user guide)

See `PIPELINE_IMPLEMENTATION.md` for technical summary and `PIPELINE_UI_GUIDE.md` for user documentation.

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

## Implementation Notes for Future Development

### Architecture Overview

**Service Layer**:
- `PipelineExecutionService` - Core execution engine, handles batching and orchestration
- `PipelineDependencyService` - DAG resolution using Kahn's algorithm, detects cycles
- `PipelineTriggerService` - Hooks for automatic pipeline triggering on entity changes

**Job Layer** (all queue-based):
- `RunPipelineBatch` - Execute pipeline for all/filtered entities (default batch size: 200)
- `RunPipelineForEntity` - Execute for single entity (triggered on entity save)
- `RunPipelineEvals` - Run all eval test cases for a pipeline

**Model Layer**:
- `Pipeline` - Main model with relationships: `attribute`, `entityType`, `modules`, `runs`, `evals`
- `PipelineModule` - Stores module config, auto-bumps pipeline version on update
- `PipelineRun` - Execution history with stats (processed, failed, tokens, duration)
- `PipelineEval` - Test cases with `isPassing()` helper method

### Module Development Guide

#### Creating a New Module

All modules must:
1. Implement `PipelineModuleInterface` (or extend `AbstractPipelineModule`)
2. Register in `AppServiceProvider::boot()` via `PipelineModuleRegistry`
3. Provide static `definition()`, `form()`, and `getInputAttributes()` methods
4. Implement `process(PipelineContext): PipelineResult`

**Example: New Lookup Module**

```php
<?php

namespace App\Pipelines\Modules;

use App\Pipelines\AbstractPipelineModule;
use App\Pipelines\Data\{PipelineContext, PipelineModuleDefinition, PipelineResult};
use Filament\Forms\Components\{Select, TextInput};
use Filament\Forms\Form;
use Illuminate\Support\Collection;

class LookupTableModule extends AbstractPipelineModule
{
    public static function definition(): PipelineModuleDefinition
    {
        return new PipelineModuleDefinition(
            id: 'lookup_table',
            label: 'Lookup Table',
            description: 'Map input values to output values via lookup table',
            type: 'processor', // or 'source'
        );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('input_key')
                ->label('Input Key')
                ->required()
                ->helperText('Which input to use for lookup'),
            
            TextInput::make('table_name')
                ->label('Lookup Table')
                ->required(),
        ]);
    }

    public static function getInputAttributes(array $settings): Collection
    {
        // Return collection of attribute IDs this module depends on
        // For processors, typically return empty (sources provide inputs)
        return collect();
    }

    public function validateSettings(array $data): array
    {
        return $this->validate($data, [
            'input_key' => 'required|string',
            'table_name' => 'required|string',
        ]);
    }

    public function process(PipelineContext $context): PipelineResult
    {
        try {
            $inputKey = $this->setting('input_key');
            $tableName = $this->setting('table_name');
            $inputValue = $context->input($inputKey);
            
            // Your lookup logic here
            $result = $this->performLookup($tableName, $inputValue);
            
            return PipelineResult::ok(
                value: $result['value'],
                confidence: 1.0,
                justification: "Looked up from {$tableName}",
            );
        } catch (\Exception $e) {
            return PipelineResult::error('Lookup failed: ' . $e->getMessage());
        }
    }
    
    // Optional: Override for batched processing
    public function processBatch(array $contexts): array
    {
        // Batch lookup for better performance
        // Return array of PipelineResult objects
    }
}
```

**Register in AppServiceProvider**:

```php
public function boot(): void
{
    $registry = app(PipelineModuleRegistry::class);
    $registry->register(LookupTableModule::class);
}
```

### Key Implementation Patterns

#### 1. Input Hash Calculation

Pipelines track input changes via SHA-256 hash. The hash includes:
- Source module outputs (all input attribute values)
- Module settings (entire settings array)
- Pipeline version

Implementation in `PipelineExecutionService::calculateInputHash()`:

```php
$data = [
    'inputs' => $inputs,
    'settings' => $this->serializeModuleSettings($pipeline),
    'version' => $pipeline->pipeline_version,
];
return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
```

Entities are skipped if:
- `input_hash` matches AND
- `pipeline_version` matches

Force re-run by:
- Bumping pipeline version (auto on module changes)
- Manual "Run Now" (ignores hash)
- Changing any source attribute value

#### 2. Version Bumping Strategy

Pipeline version auto-increments when:
- Modules are added/removed/reordered
- Module settings change
- Triggered in `EditPipeline::afterSave()`

This invalidates all cached attribute values, forcing re-execution.

**Important**: Version is NOT bumped for:
- Pipeline name changes
- Eval additions/changes
- Run statistics updates

#### 3. Batch Processing Pattern

Default flow (single-entity processing):

```php
public function process(PipelineContext $context): PipelineResult
{
    // Process one entity
    return PipelineResult::ok($value, $confidence, $justification);
}
```

Optimized batch processing (override in module):

```php
public function processBatch(array $contexts): array
{
    // Process multiple entities at once
    // Example: Single OpenAI API call with multiple prompts
    // Example: Bulk database query
    
    $results = [];
    foreach ($contexts as $context) {
        $results[] = PipelineResult::ok(...);
    }
    return $results;
}
```

**When to use batching**:
- ✅ AI API calls (reduce network overhead)
- ✅ Database lookups (batch queries)
- ✅ Node.js execution (already batched for CalculationModule)
- ❌ Simple transformations (batching overhead not worth it)

#### 4. Node.js Helper Integration

The `CalculationProcessorModule` uses a sandboxed Node.js helper (`resources/node/pipeline-runner.cjs`).

**Security constraints**:
- No network access
- No filesystem access
- 10-second timeout per batch
- Limited to Node built-ins (no npm packages)
- Uses `vm` module for sandboxing

**Available in user code**:
```javascript
// Global variables injected per item:
$json        // Object: all inputs { name: value, ... }
$value       // Current value in pipeline
$meta        // Metadata object

// Must return:
{
    value: <any>,           // Required: the computed value
    justification: <string>, // Required: explanation
    confidence: <number>     // Required: 0.0 to 1.0
}
```

**To modify Node helper behavior**, edit `resources/node/pipeline-runner.cjs`. The protocol is:
- Input: JSON via stdin with `{ code, items: [...] }`
- Output: JSON to stdout with `{ results: [...] }`

#### 5. Dependency Resolution

`PipelineDependencyService` prevents circular dependencies and computes execution order.

**Kahn's Algorithm** (topological sort):
1. Build adjacency list of pipeline dependencies
2. Find pipelines with no dependencies (in-degree 0)
3. Process queue, decrementing in-degrees
4. If not all processed → cycle detected

**Usage**:

```php
$service = app(PipelineDependencyService::class);

// Validate before saving
$errors = $service->validatePipeline($pipeline);
if (!empty($errors)) {
    throw new RuntimeException(implode(', ', $errors));
}

// Get execution order for nightly scheduler
$ordered = $service->getExecutionOrder($entityType);
foreach ($ordered as $pipeline) {
    RunPipelineBatch::dispatch($pipeline, 'schedule');
}
```

**Edge case**: If Pipeline A depends on Pipeline B (uses B's output as input), B must complete before A starts. The scheduler respects this ordering.

#### 6. Eval Pass/Fail Logic

In `PipelineEval::isPassing()`:

```php
public function isPassing(): bool
{
    if ($this->actual_output === null || $this->desired_output === null) {
        return false;
    }
    
    // Exact JSON match (strict)
    return json_encode($this->actual_output) === json_encode($this->desired_output);
}
```

**Current limitations**:
- ❌ No fuzzy matching (e.g., "Hello" vs "hello")
- ❌ No numeric tolerance (e.g., 0.95 vs 0.9500001)
- ❌ No array order independence

**To add flexible matching**, override `isPassing()` or add a `comparison_mode` setting to evals table.

### Extension Points

#### 1. Adding More Module Types

Current modules are just examples. Easy to add:
- **API Integration Module**: Call external APIs, cache responses
- **Regex Extraction Module**: Parse structured text
- **Image Analysis Module**: Vision API integration
- **Translation Module**: Multi-language support
- **Sentiment Analysis**: NLP integration

All follow the same `AbstractPipelineModule` pattern.

#### 2. Custom Triggers

Beyond entity save and nightly scheduler, you can trigger pipelines:

```php
use App\Services\PipelineTriggerService;

// In any controller/job/event listener:
$service = app(PipelineTriggerService::class);
$service->triggerPipelinesForEntity($entity); // All affected pipelines
```

Example: Trigger when external data changes, cron job runs, webhook received, etc.

#### 3. UI Enhancements

Current UI is functional but could be extended:
- **Pipeline run history page**: See all runs, not just last
- **Entity-specific eval creation**: Button on entity edit form
- **Visual DAG diagram**: Show pipeline dependencies graphically
- **Batch size configuration**: Per-pipeline or global setting UI
- **Cost estimation**: Preview token costs before running
- **Pause/resume**: Long-running pipeline control

UI files:
- `app/Filament/Resources/PipelineResource.php` - Main resource
- `app/Filament/Resources/PipelineResource/Pages/CreatePipeline.php`
- `app/Filament/Resources/PipelineResource/Pages/EditPipeline.php`
- `app/Filament/Resources/PipelineResource/Pages/ListPipelines.php`

#### 4. Monitoring & Alerting

Add monitoring hooks:
- Slack/email notifications on pipeline failures
- Token usage alerts (e.g., >$100/day)
- Eval failure rate tracking
- Performance degradation detection

Hook into `PipelineRun` events or add observers.

#### 5. Advanced AI Features

Current AI module is basic. Could add:
- **Few-shot examples**: Include sample inputs/outputs in prompt
- **Chain of thought**: Multi-step reasoning
- **Model selection per entity**: Different models for different product types
- **Temperature/top_p control**: Exposed in module settings
- **Streaming responses**: For long outputs
- **Vision support**: Image inputs for product descriptions

Extend `AiPromptProcessorModule` or create new AI-specific modules.

### Performance Considerations

**Current bottlenecks**:
1. **AI API latency**: 1-3 seconds per entity
   - Mitigation: Batch size tuning (default 200)
   - Future: Parallel execution with rate limiting

2. **Database queries**: N+1 when loading attributes
   - Mitigation: `AttributesSourceModule::loadInputsForEntities()` already batches
   - Future: Eager loading in execution service

3. **Node.js overhead**: Process spawn per batch
   - Mitigation: Batch size of 200, reuses process
   - Future: Persistent Node.js worker pool

**Optimization strategies**:
- ✅ Batch processing (already implemented)
- ✅ Input hash skipping (already implemented)
- ⚠️ Parallel pipeline execution (future: multiple queue workers)
- ⚠️ Caching AI responses (future: semantic cache)
- ⚠️ Incremental updates (future: only changed entities)

### Testing Strategy

**Test structure**:
```
tests/
├── Unit/
│   ├── PipelineModuleRegistryTest.php   # Module registration
│   ├── PipelineDataClassesTest.php      # DTOs
│   └── NodePipelineRunnerTest.php       # Node.js helper
├── Feature/
│   ├── PipelineModelTest.php            # Models & relationships
│   ├── PipelineDependencyTest.php       # DAG & cycles
│   └── PipelineExecutionTest.php        # End-to-end execution
```

**Adding tests for new modules**:

```php
class CustomModuleTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_can_process_single_item(): void
    {
        $module = new CustomModule($this->createModuleModel([
            'setting1' => 'value1',
        ]));
        
        $context = new PipelineContext(
            entityId: '01JAXXX',
            attributeId: 1,
            inputs: ['key' => 'value'],
            batchIndex: 0,
            pipelineVersion: 1,
        );
        
        $result = $module->process($context);
        
        $this->assertEquals('ok', $result->status);
        $this->assertNotNull($result->value);
    }
}
```

**Run pipeline tests**:
```bash
docker exec spim_app bash -c "cd /var/www/html && php artisan test --filter Pipeline"
```

### Common Issues & Solutions

#### Issue: "First module must be a source module"
**Cause**: Builder allows adding processors first  
**Solution**: Validation in `EditPipeline::validateModuleConfiguration()` blocks save

#### Issue: Modules not appearing in Builder
**Cause**: Not registered in `PipelineModuleRegistry`  
**Solution**: Add `$registry->register(YourModule::class)` to `AppServiceProvider::boot()`

#### Issue: Evals always failing
**Cause**: Exact JSON match is strict (whitespace, order)  
**Solution**: Ensure desired_output matches exact format, or customize `isPassing()` method

#### Issue: Pipeline not running on entity save
**Cause**: `PipelineTriggerService` not hooked to entity save event  
**Solution**: Add observer or event listener to call `triggerPipelinesForEntity()`

#### Issue: High token costs
**Cause**: Prompts too long, or wrong model selected  
**Solution**: 
- Use GPT-4o Mini instead of GPT-4o
- Optimize prompts (remove redundant text)
- Check Statistics tab for token usage
- Add token budget alerts

### Migration & Rollback

**Migration**: `2025_10_15_000000_create_pipeline_tables.php`

**To rollback**:
```bash
php artisan migrate:rollback --step=1
```

**Safe rollback**: Pipelines are independent. Rollback doesn't affect:
- Existing attributes
- Entity data
- Sync functionality

**Data cleanup** (if needed):
```php
// Remove pipeline_version from eav_versioned
DB::table('eav_versioned')->update(['pipeline_version' => null, 'input_hash' => null]);
```

### Future Enhancements (Not Implemented)

These were considered but deferred:

1. **Per-entity resilience**: Currently, first failure aborts entire batch. Could allow partial completion.

2. **Soft comparison for evals**: Fuzzy matching, numeric tolerance, array order independence.

3. **Cost dashboards**: Visualize token usage trends, per-pipeline costs, forecasting.

4. **Manual retry UI**: Retry failed entities without re-running entire pipeline.

5. **Pipeline templates**: Pre-built pipelines for common use cases (e.g., "SEO Description Generator").

6. **A/B testing**: Run multiple pipeline versions, compare results.

7. **Confidence-based routing**: Different pipelines based on input confidence scores.

8. **Multi-attribute pipelines**: Generate multiple attributes in one run (currently 1:1).

9. **Pipeline chaining UI**: Visual editor for complex multi-step workflows.

10. **Real-time execution**: Non-batched, immediate results (currently queue-based).

## Open Questions / Follow-ups
- How do we surface cost/token trends to business users (dashboards vs. exports)?
- Do we need a manual retry flow for partially failed runs once we introduce per-entity resilience?
- Should eval desired outputs support soft comparisons (e.g. tolerance for numeric deltas) in future?
- Should we add a persistent Node.js worker pool to eliminate process spawn overhead?
- Would semantic caching of AI responses provide meaningful cost savings?
