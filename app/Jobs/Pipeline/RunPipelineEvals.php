<?php

namespace App\Jobs\Pipeline;

use App\Models\Pipeline;
use App\Services\PipelineExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunPipelineEvals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes max

    public function __construct(
        public Pipeline $pipeline,
    ) {}

    public function handle(PipelineExecutionService $executionService): void
    {
        try {
            $stats = $executionService->executeEvals($this->pipeline);

            Log::info('Pipeline evals completed', [
                'pipeline_id' => $this->pipeline->id,
                'passing' => $stats['passing'],
                'failing' => $stats['failing'],
                'total' => $stats['total'],
            ]);
        } catch (\Exception $e) {
            Log::error('Pipeline evals job failed', [
                'pipeline_id' => $this->pipeline->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get tags for queue monitoring
     */
    public function tags(): array
    {
        return [
            'pipeline',
            'evals',
            'pipeline:'.$this->pipeline->id,
        ];
    }
}
