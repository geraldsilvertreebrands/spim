<?php

namespace App\Console\Commands;

use App\Jobs\Pipeline\RunPipelineBatch;
use App\Jobs\Pipeline\RunPipelineEvals;
use App\Models\EntityType;
use App\Models\Pipeline;
use App\Services\PipelineDependencyService;
use Illuminate\Console\Command;

class RunNightlyPipelines extends Command
{
    protected $signature = 'pipelines:run-nightly
                           {--entity-type= : Specific entity type ID to process}
                           {--pipeline= : Specific pipeline ID to process}
                           {--skip-evals : Skip running evals}';

    protected $description = 'Run all pipelines for all entities (nightly job)';

    public function handle(PipelineDependencyService $dependencyService): int
    {
        $this->info('Starting nightly pipeline execution...');

        // Get entity types to process
        $entityTypes = $this->option('entity-type')
            ? EntityType::where('id', $this->option('entity-type'))->get()
            : EntityType::all();

        $totalPipelines = 0;

        foreach ($entityTypes as $entityType) {
            $this->info("Processing entity type: {$entityType->name}");

            // Get pipelines for this entity type
            $pipelines = $this->option('pipeline')
                ? Pipeline::where('id', $this->option('pipeline'))->get()
                : Pipeline::where('entity_type_id', $entityType->id)
                    ->with('modules', 'attribute')
                    ->get();

            if ($pipelines->isEmpty()) {
                $this->comment("  No pipelines found");
                continue;
            }

            // Get execution order based on dependencies
            try {
                $orderedPipelines = $dependencyService->getExecutionOrder($entityType);

                // Filter to only requested pipelines if specified
                if ($this->option('pipeline')) {
                    $requestedId = $this->option('pipeline');
                    $orderedPipelines = $orderedPipelines->filter(
                        fn($p) => $p->id === $requestedId
                    );
                }

                $this->info("  Found {$orderedPipelines->count()} pipelines to run");

                foreach ($orderedPipelines as $pipeline) {
                    $this->comment("  Queuing pipeline: {$pipeline->attribute->name}");

                    // Queue batch job
                    RunPipelineBatch::dispatch(
                        pipeline: $pipeline,
                        triggeredBy: 'schedule',
                    );

                    // Queue eval job unless skipped
                    if (!$this->option('skip-evals')) {
                        RunPipelineEvals::dispatch($pipeline);
                    }

                    $totalPipelines++;
                }
            } catch (\Exception $e) {
                $this->error("  Error processing entity type: " . $e->getMessage());
                continue;
            }
        }

        $this->info("Queued {$totalPipelines} pipeline jobs");

        return self::SUCCESS;
    }
}

