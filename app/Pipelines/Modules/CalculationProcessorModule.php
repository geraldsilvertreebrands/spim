<?php

namespace App\Pipelines\Modules;

use App\Pipelines\AbstractPipelineModule;
use App\Pipelines\Data\PipelineContext;
use App\Pipelines\Data\PipelineModuleDefinition;
use App\Pipelines\Data\PipelineResult;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CalculationProcessorModule extends AbstractPipelineModule
{
    public static function definition(): PipelineModuleDefinition
    {
        return new PipelineModuleDefinition(
            id: 'calculation',
            label: 'Calculation (JavaScript)',
            description: 'Execute JavaScript code to transform inputs',
            type: 'processor',
        );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Textarea::make('code')
                ->label('JavaScript Code')
                ->required()
                ->rows(15)
                ->helperText('JavaScript code to process the inputs. Use $json to access inputs as an object. Return an object with { value, justification, confidence }.')
                ->placeholder(<<<'JS'
// Example: Calculate total from quantity and price
const total = ($json.quantity || 0) * ($json.price || 0);

return {
    value: total,
    justification: `Calculated from ${$json.quantity} × ${$json.price}`,
    confidence: 1.0
};
JS
                ),
        ]);
    }

    public function validateSettings(array $data): array
    {
        return $this->validate($data, [
            'code' => 'required|string',
        ]);
    }

    public function process(PipelineContext $context): PipelineResult
    {
        try {
            $result = $this->executeCode($context);

            $value = $result['value'] ?? null;
            $justification = $result['justification'] ?? null;
            $confidence = $result['confidence'] ?? null;
            $meta = $result['meta'] ?? [];

            return PipelineResult::ok($value, $confidence, $justification, $meta);
        } catch (\Exception $e) {
            return PipelineResult::error('Calculation failed: ' . $e->getMessage());
        }
    }

    /**
     * Process batch of items through Node.js
     */
    public function processBatch(array $contexts): array
    {
        try {
            $code = $this->setting('code');

            // Build batch payload
            $items = [];
            foreach ($contexts as $index => $context) {
                $items[] = [
                    'index' => $index,
                    'entityId' => $context->entityId,
                    'inputs' => $context->allInputs(),
                ];
            }

            $payload = [
                'code' => $code,
                'items' => $items,
            ];

            // Call Node helper
            $results = $this->callNodeHelper($payload);

            // Map results back to PipelineResult objects
            $pipelineResults = [];
            foreach ($contexts as $index => $context) {
                $result = $results[$index] ?? null;

                if (!$result) {
                    $pipelineResults[] = PipelineResult::error('No result returned for entity');
                    continue;
                }

                if (isset($result['error'])) {
                    $pipelineResults[] = PipelineResult::error($result['error']);
                    continue;
                }

                $pipelineResults[] = PipelineResult::ok(
                    value: $result['value'] ?? null,
                    confidence: $result['confidence'] ?? null,
                    justification: $result['justification'] ?? null,
                    meta: $result['meta'] ?? []
                );
            }

            return $pipelineResults;
        } catch (\Exception $e) {
            // If batch fails, return error for all items
            return array_map(
                fn() => PipelineResult::error('Batch calculation failed: ' . $e->getMessage()),
                $contexts
            );
        }
    }

    /**
     * Execute single item code
     */
    protected function executeCode(PipelineContext $context): array
    {
        $code = $this->setting('code');

        $payload = [
            'code' => $code,
            'items' => [
                [
                    'index' => 0,
                    'entityId' => $context->entityId,
                    'inputs' => $context->allInputs(),
                ],
            ],
        ];

        $results = $this->callNodeHelper($payload);

        if (empty($results[0])) {
            throw new \RuntimeException('No result returned from Node helper');
        }

        if (isset($results[0]['error'])) {
            throw new \RuntimeException($results[0]['error']);
        }

        return $results[0];
    }

    /**
     * Call the Node.js helper script
     */
    protected function callNodeHelper(array $payload): array
    {
        $helperPath = base_path('resources/node/pipeline-runner.cjs');

        if (!file_exists($helperPath)) {
            throw new \RuntimeException('Node.js pipeline runner not found at: ' . $helperPath);
        }

        // Create process
        $process = new Process(
            ['node', $helperPath],
            base_path(),
            null,
            json_encode($payload),
            10.0 // 10 second timeout per batch
        );

        try {
            $process->mustRun();
            $output = $process->getOutput();

            $result = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON from Node helper: ' . $output);
            }

            if (isset($result['error'])) {
                throw new \RuntimeException('Node helper error: ' . $result['error']);
            }

            return $result['results'] ?? [];
        } catch (ProcessFailedException $e) {
            throw new \RuntimeException(
                'Node process failed: ' . $e->getMessage() . "\nOutput: " . $process->getOutput() . "\nError: " . $process->getErrorOutput()
            );
        }
    }
}

