<?php

namespace App\Jobs\Pipeline;

use App\Models\Entity;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Services\PipelineExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunPipelineBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour max

    public function __construct(
        public Pipeline $pipeline,
        public string $triggeredBy = 'manual', // 'schedule', 'entity_save', 'manual'
        public ?string $triggerReference = null, // entity_id or user_id
        public int $batchSize = 200,
        public ?int $maxEntities = null, // Limit number of entities to process
        public bool $force = false, // Force reprocess even if inputs unchanged
    ) {
    }

    public function handle(PipelineExecutionService $executionService): void
    {
        // Create pipeline run record
        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_version' => $this->pipeline->pipeline_version,
            'triggered_by' => $this->triggeredBy,
            'trigger_reference' => $this->triggerReference,
            'status' => 'running',
            'batch_size' => $this->batchSize,
            'started_at' => now(),
        ]);

        try {
            // Get all entities for this entity type
            $query = Entity::where('entity_type_id', $this->pipeline->entity_type_id)
                ->orderBy('id');

            // If there's an entity filter, apply it at the query level
            if ($this->pipeline->entity_filter) {
                $entityIds = $this->applyEntityFilter($query);
                Log::info('Applied entity filter', [
                    'pipeline_id' => $this->pipeline->id,
                    'filter' => $this->pipeline->entity_filter,
                    'entities_after_filter' => $entityIds->count(),
                ]);
            } else {
                $entityIds = $query->pluck('id');
            }

            // Apply max entities limit after filtering
            $beforeLimit = $entityIds->count();
            if ($this->maxEntities !== null && $entityIds->count() > $this->maxEntities) {
                $entityIds = $entityIds->take($this->maxEntities);
            }

            Log::info('Pipeline batch starting', [
                'pipeline_id' => $this->pipeline->id,
                'total_entities_matching_filter' => $beforeLimit,
                'entities_to_process' => $entityIds->count(),
                'max_entities_limit' => $this->maxEntities,
                'force' => $this->force,
            ]);

            if ($entityIds->isEmpty()) {
                $run->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                return;
            }

            // Process in batches
            $chunks = $entityIds->chunk($this->batchSize);

            foreach ($chunks as $chunk) {
                $stats = $executionService->executeBatch($this->pipeline, $chunk, $run, $this->force);

                // If any failures, abort
                if ($stats['failed'] > 0) {
                    break;
                }
            }

            // Mark run as completed
            $run->markCompleted();

            // Calculate total skipped stats
            $totalFiltered = $entityIds->count() - $run->entities_processed - $run->entities_failed - $run->entities_skipped;

            Log::info('Pipeline batch completed', [
                'pipeline_id' => $this->pipeline->id,
                'run_id' => $run->id,
                'processed' => $run->entities_processed,
                'failed' => $run->entities_failed,
                'skipped_up_to_date' => $run->entities_skipped,
                'total_candidates' => $entityIds->count(),
                'force_reprocess' => $this->force,
            ]);
        } catch (\Exception $e) {
            $run->markFailed($e->getMessage());

            Log::error('Pipeline batch job failed', [
                'pipeline_id' => $this->pipeline->id,
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get tags for queue monitoring
     */
    public function tags(): array
    {
        return [
            'pipeline',
            'pipeline:' . $this->pipeline->id,
            'triggered:' . $this->triggeredBy,
        ];
    }

    /**
     * Apply entity filter to get filtered entity IDs
     */
    protected function applyEntityFilter($baseQuery): \Illuminate\Support\Collection
    {
        $filter = $this->pipeline->entity_filter;
        $attributeId = $filter['attribute_id'] ?? null;
        $operator = $filter['operator'] ?? '=';
        $value = $filter['value'] ?? null;

        if (!$attributeId) {
            return $baseQuery->pluck('id');
        }

        // Get all entity IDs from base query
        $allEntityIds = $baseQuery->pluck('id');

        // Build filter query on eav_versioned
        $query = \DB::table('eav_versioned')
            ->whereIn('entity_id', $allEntityIds)
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
                // Unknown operator, return all
                return $allEntityIds;
        }

        return $query->pluck('entity_id');
    }
}

