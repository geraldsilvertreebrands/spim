<?php

namespace App\Services;

use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Pipelines\Data\PipelineContext;
use App\Pipelines\Modules\AttributesSourceModule;
use App\Pipelines\PipelineModuleRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PipelineExecutionService
{
    public function __construct(
        protected PipelineModuleRegistry $registry,
        protected EavWriter $eavWriter,
    ) {
    }

    /**
     * Execute a pipeline for a batch of entities
     *
     * @param Pipeline $pipeline
     * @param Collection|array $entityIds
     * @param PipelineRun|null $run Optional run to track execution
     * @param bool $force Force reprocess even if inputs haven't changed
     * @return array Stats array with processed, failed, skipped counts
     */
    public function executeBatch(Pipeline $pipeline, Collection|array $entityIds, ?PipelineRun $run = null, bool $force = false): array
    {
        $entityIds = collect($entityIds);
        $stats = [
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'skipped_up_to_date' => 0, // Skipped because inputs/version unchanged
            'tokens_in' => 0,
            'tokens_out' => 0,
        ];

        if ($entityIds->isEmpty()) {
            return $stats;
        }

        try {
            // Apply entity filter if configured (will be idempotent if Job already filtered)
            if ($pipeline->entity_filter) {
                $filteredIds = $this->filterByCondition($entityIds, $pipeline->entity_filter);
                $filterSkipped = $entityIds->count() - $filteredIds->count();
                $stats['skipped'] += $filterSkipped;
                $entityIds = $filteredIds;

                if ($entityIds->isEmpty()) {
                    return $stats;
                }
            }

            // Load modules
            $modules = $pipeline->modules()->orderBy('order')->get();

            if ($modules->isEmpty()) {
                throw new \RuntimeException('Pipeline has no modules');
            }

            // Validate pipeline structure
            $errors = $this->registry->validatePipeline($modules);
            if (!empty($errors)) {
                throw new \RuntimeException('Invalid pipeline: ' . implode(', ', $errors));
            }

            // First module must be source
            $sourceModule = $modules->first();
            $sourceInstance = $this->registry->make($sourceModule->module_class, $sourceModule);

            if (!($sourceInstance instanceof AttributesSourceModule)) {
                throw new \RuntimeException('First module must be an AttributesSourceModule');
            }

            // Load inputs for all entities in batch
            $inputsMap = $sourceInstance->loadInputsForEntities($entityIds->toArray());

            // Filter entities by those that need processing (unless forcing)
            if ($force) {
                $entitiesToProcess = $inputsMap;
                Log::info('Force reprocess enabled', [
                    'pipeline_id' => $pipeline->id,
                    'entities_to_process' => count($inputsMap),
                ]);
            } else {
                $entitiesToProcess = $this->filterEntitiesNeedingProcessing(
                    $pipeline,
                    $entityIds,
                    $inputsMap
                );
                $upToDateCount = $entityIds->count() - count($entitiesToProcess);
                $stats['skipped_up_to_date'] = $upToDateCount;
                $stats['skipped'] += $upToDateCount;

                Log::info('Filtering entities needing processing', [
                    'pipeline_id' => $pipeline->id,
                    'input_entities' => $entityIds->count(),
                    'needs_processing' => count($entitiesToProcess),
                    'skipped_up_to_date' => $upToDateCount,
                ]);
            }

            // Process each entity
            foreach ($entitiesToProcess as $entityId => $inputs) {
                try {
                    $result = $this->executeForEntity(
                        $pipeline,
                        $entityId,
                        $inputs,
                        $modules->skip(1) // Skip source module
                    );

                    // Save result
                    $this->saveResult($pipeline, $entityId, $inputs, $result);

                    // Track tokens
                    if (isset($result->meta['tokens_in'])) {
                        $stats['tokens_in'] += $result->meta['tokens_in'];
                    }
                    if (isset($result->meta['tokens_out'])) {
                        $stats['tokens_out'] += $result->meta['tokens_out'];
                    }

                    $stats['processed']++;

                    if ($run) {
                        $run->incrementProcessed();
                    }
                } catch (\Exception $e) {
                    Log::error('Pipeline execution failed for entity', [
                        'pipeline_id' => $pipeline->id,
                        'entity_id' => $entityId,
                        'error' => $e->getMessage(),
                    ]);

                    $stats['failed']++;

                    if ($run) {
                        $run->incrementFailed();
                    }

                    // Abort on first failure as per spec
                    throw new \RuntimeException(
                        "Pipeline aborted at entity {$entityId}: " . $e->getMessage()
                    );
                }
            }

            // Update run with token usage
            if ($run && ($stats['tokens_in'] > 0 || $stats['tokens_out'] > 0)) {
                $run->addTokens($stats['tokens_in'], $stats['tokens_out']);
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error('Pipeline batch execution failed', [
                'pipeline_id' => $pipeline->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute pipeline for a single entity
     *
     * @param Pipeline $pipeline
     * @param string $entityId
     * @return array Stats array
     */
    public function executeForSingleEntity(Pipeline $pipeline, string $entityId): array
    {
        return $this->executeBatch($pipeline, collect([$entityId]));
    }

    /**
     * Execute processor modules for a single entity
     */
    protected function executeForEntity(Pipeline $pipeline, string $entityId, array $inputs, Collection $processorModules)
    {
        $context = new PipelineContext(
            entityId: $entityId,
            attributeId: $pipeline->attribute_id,
            inputs: $inputs,
            pipelineVersion: $pipeline->pipeline_version,
            settings: [],
        );

        $result = null;

        foreach ($processorModules as $module) {
            $instance = $this->registry->make($module->module_class, $module);

            // Update context with module settings
            $context = new PipelineContext(
                entityId: $context->entityId,
                attributeId: $context->attributeId,
                inputs: $context->inputs,
                pipelineVersion: $context->pipelineVersion,
                settings: $module->settings ?? [],
                meta: $context->meta,
            );

            $result = $instance->process($context);

            if ($result->isError()) {
                throw new \RuntimeException($result->getErrorMessages());
            }

            if ($result->isSkipped()) {
                // If skipped, stop processing this entity
                return $result;
            }

            // Update context inputs with result for next module (if any)
            if ($processorModules->last() !== $module) {
                $context = $context->mergeInputs(['_previous_value' => $result->value]);
            }
        }

        return $result;
    }

    /**
     * Filter entities that need processing based on input hash and pipeline version
     */
    protected function filterEntitiesNeedingProcessing(Pipeline $pipeline, Collection $entityIds, array $inputsMap): array
    {
        // Get existing attribute values with hashes
        $existingValues = DB::table('eav_versioned')
            ->whereIn('entity_id', $entityIds)
            ->where('attribute_id', $pipeline->attribute_id)
            ->get()
            ->keyBy('entity_id');

        $entitiesToProcess = [];

        foreach ($inputsMap as $entityId => $inputs) {
            $inputHash = $this->calculateInputHash($inputs, $pipeline);
            $existing = $existingValues->get($entityId);

            // Process if:
            // - No existing value
            // - Input hash changed
            // - Pipeline version is newer than stored version
            if (!$existing
                || $existing->input_hash !== $inputHash
                || $existing->pipeline_version < $pipeline->pipeline_version
            ) {
                $entitiesToProcess[$entityId] = $inputs;
            }
        }

        return $entitiesToProcess;
    }

    /**
     * Calculate stable hash of inputs for change detection
     */
    protected function calculateInputHash(array $inputs, Pipeline $pipeline): string
    {
        // Sort inputs by key for consistency
        ksort($inputs);

        // Include pipeline version in hash
        $hashData = [
            'inputs' => $inputs,
            'pipeline_version' => $pipeline->pipeline_version,
        ];

        return hash('sha256', json_encode($hashData));
    }

    /**
     * Save pipeline result to database
     */
    protected function saveResult(Pipeline $pipeline, string $entityId, array $inputs, $result): void
    {
        $inputHash = $this->calculateInputHash($inputs, $pipeline);

        // Convert value to string for storage
        $value = $result->value;
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        $this->eavWriter->upsertVersioned(
            entityId: $entityId,
            attributeId: $pipeline->attribute_id,
            newValue: $value,
            opts: [
                'input_hash' => $inputHash,
                'justification' => $result->justification,
                'confidence' => $result->confidence,
                'meta' => array_merge($result->meta, [
                    'pipeline_version' => $pipeline->pipeline_version,
                ]),
            ]
        );

        // Also update the pipeline_version field
        DB::table('eav_versioned')
            ->where('entity_id', $entityId)
            ->where('attribute_id', $pipeline->attribute_id)
            ->update(['pipeline_version' => $pipeline->pipeline_version]);
    }

    /**
     * Execute evals for a pipeline
     *
     * @return array Array with passing/failing counts
     */
    /**
     * Execute a single evaluation immediately
     */
    public function executeSingleEval(\App\Models\PipelineEval $eval): array
    {
        $pipeline = $eval->pipeline;

        try {
            // Load modules
            $modules = $pipeline->modules()->orderBy('order')->get();

            // Load inputs from source module
            $sourceModule = $modules->first();
            $sourceInstance = $this->registry->make($sourceModule->module_class, $sourceModule);
            $inputsMap = $sourceInstance->loadInputsForEntities([$eval->entity_id]);
            $inputs = $inputsMap[$eval->entity_id] ?? [];

            // Execute pipeline
            $result = $this->executeForEntity(
                $pipeline,
                $eval->entity_id,
                $inputs,
                $modules->skip(1)
            );

            // Update eval with actual output
            $actualOutput = is_array($result->value) ? $result->value : ['value' => $result->value];
            $eval->updateActualOutput(
                $actualOutput,
                $result->justification,
                $result->confidence
            );

            // Update hash
            $eval->input_hash = $this->calculateInputHash($inputs, $pipeline);
            $eval->save();

            return [
                'success' => true,
                'passing' => $eval->isPassing(),
                'actual_output' => $actualOutput,
                'justification' => $result->justification,
                'confidence' => $result->confidence,
            ];
        } catch (\Exception $e) {
            Log::error('Eval execution failed', [
                'eval_id' => $eval->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function executeEvals(Pipeline $pipeline): array
    {
        $evals = $pipeline->evals;
        $stats = ['passing' => 0, 'failing' => 0, 'total' => $evals->count()];

        foreach ($evals as $eval) {
            $result = $this->executeSingleEval($eval);

            if ($result['success']) {
                if ($result['passing']) {
                    $stats['passing']++;
                } else {
                    $stats['failing']++;
                }
            } else {
                $stats['failing']++;
            }
        }

        return $stats;
    }

    /**
     * Filter entities by condition
     *
     * @param Collection $entityIds
     * @param array $filter Filter configuration
     * @return Collection Filtered entity IDs
     */
    protected function filterByCondition(Collection $entityIds, array $filter): Collection
    {
        if (empty($filter) || !isset($filter['attribute_id'])) {
            return $entityIds;
        }

        $attributeId = $filter['attribute_id'];
        $operator = $filter['operator'] ?? '=';
        $value = $filter['value'] ?? null;

        $query = DB::table('eav_versioned')
            ->whereIn('entity_id', $entityIds)
            ->where('attribute_id', $attributeId);

        // Apply operator
        switch ($operator) {
            case '=':
                $query->where('value_current', $value);
                break;
            case '!=':
                $query->where('value_current', '!=', $value);
                break;
            case '>':
                $query->where('value_current', '>', $value);
                break;
            case '>=':
                $query->where('value_current', '>=', $value);
                break;
            case '<':
                $query->where('value_current', '<', $value);
                break;
            case '<=':
                $query->where('value_current', '<=', $value);
                break;
            case 'in':
                $query->whereIn('value_current', is_array($value) ? $value : [$value]);
                break;
            case 'not_in':
                $query->whereNotIn('value_current', is_array($value) ? $value : [$value]);
                break;
            case 'null':
                $query->whereNull('value_current');
                break;
            case 'not_null':
                $query->whereNotNull('value_current');
                break;
            case 'contains':
                $query->where('value_current', 'LIKE', '%' . $value . '%');
                break;
            default:
                // Unknown operator, return unfiltered
                return $entityIds;
        }

        return $query->pluck('entity_id');
    }
}

