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

            if ($this->maxEntities !== null) {
                $query->limit($this->maxEntities);
            }

            $entityIds = $query->pluck('id');

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
                $stats = $executionService->executeBatch($this->pipeline, $chunk, $run);

                // If any failures, abort
                if ($stats['failed'] > 0) {
                    break;
                }
            }

            // Mark run as completed
            $run->markCompleted();

            Log::info('Pipeline batch completed', [
                'pipeline_id' => $this->pipeline->id,
                'run_id' => $run->id,
                'processed' => $run->entities_processed,
                'failed' => $run->entities_failed,
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
}

