<?php

namespace App\Jobs\Pipeline;

use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Services\PipelineExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunPipelineForEntity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes max

    public function __construct(
        public Pipeline $pipeline,
        public string $entityId,
        public string $triggeredBy = 'entity_save',
        public ?string $triggerReference = null,
    ) {
    }

    public function handle(PipelineExecutionService $executionService): void
    {
        // Create pipeline run record for this single entity
        $run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_version' => $this->pipeline->pipeline_version,
            'triggered_by' => $this->triggeredBy,
            'trigger_reference' => $this->triggerReference ?? $this->entityId,
            'status' => 'running',
            'batch_size' => 1,
            'started_at' => now(),
        ]);

        try {
            $stats = $executionService->executeForSingleEntity($this->pipeline, $this->entityId);

            // Update run with results
            $run->update([
                'entities_processed' => $stats['processed'],
                'entities_failed' => $stats['failed'],
                'entities_skipped' => $stats['skipped'],
                'tokens_in' => $stats['tokens_in'] ?? 0,
                'tokens_out' => $stats['tokens_out'] ?? 0,
            ]);

            $run->markCompleted();

            Log::info('Pipeline execution completed for entity', [
                'pipeline_id' => $this->pipeline->id,
                'entity_id' => $this->entityId,
                'run_id' => $run->id,
            ]);
        } catch (\Exception $e) {
            $run->markFailed($e->getMessage());

            Log::error('Pipeline execution failed for entity', [
                'pipeline_id' => $this->pipeline->id,
                'entity_id' => $this->entityId,
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
            'entity:' . $this->entityId,
        ];
    }
}

