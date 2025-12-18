<?php

namespace App\Pipelines\Modules;

use App\Pipelines\AbstractPipelineModule;
use App\Pipelines\Data\PipelineContext;
use App\Pipelines\Data\PipelineModuleDefinition;
use App\Pipelines\Data\PipelineResult;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Http;

class AiPromptProcessorModule extends AbstractPipelineModule
{
    /**
     * Common JSON schema templates
     */
    protected const SCHEMA_TEMPLATES = [
        'text' => [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'string'],
                'justification' => ['type' => 'string'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            ],
            'required' => ['value', 'justification', 'confidence'],
            'additionalProperties' => false,
        ],
        'integer' => [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'integer'],
                'justification' => ['type' => 'string'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            ],
            'required' => ['value', 'justification', 'confidence'],
            'additionalProperties' => false,
        ],
        'boolean' => [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'boolean'],
                'justification' => ['type' => 'string'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            ],
            'required' => ['value', 'justification', 'confidence'],
            'additionalProperties' => false,
        ],
        'array' => [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'array', 'items' => ['type' => 'string']],
                'justification' => ['type' => 'string'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            ],
            'required' => ['value', 'justification', 'confidence'],
            'additionalProperties' => false,
        ],
    ];

    public static function definition(): PipelineModuleDefinition
    {
        return new PipelineModuleDefinition(
            id: 'ai_prompt',
            label: 'AI Prompt',
            description: 'Generate values using OpenAI with a custom prompt',
            type: 'processor',
        );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Textarea::make('prompt')
                ->label('Prompt')
                ->required()
                ->rows(5)
                ->helperText('The prompt to send to OpenAI. Input attributes will be appended automatically.'),

            Select::make('schema_template')
                ->label('Output Schema Template')
                ->options([
                    'text' => 'Text (string with justification and confidence)',
                    'integer' => 'Integer (number with justification and confidence)',
                    'boolean' => 'Boolean (true/false with justification and confidence)',
                    'array' => 'Array of strings (with justification and confidence)',
                    'custom' => 'Custom (edit JSON below)',
                ])
                ->default('text')
                ->live()
                ->required(),

            Textarea::make('output_schema')
                ->label('Output Schema (JSON)')
                ->required()
                ->rows(10)
                ->default(fn () => json_encode(self::SCHEMA_TEMPLATES['text'], JSON_PRETTY_PRINT))
                ->helperText('OpenAI-compatible JSON schema for structured output'),

            Select::make('model')
                ->label('Model')
                ->options([
                    'gpt-4o' => 'GPT-4o (latest, recommended)',
                    'gpt-4o-mini' => 'GPT-4o Mini (faster, cheaper)',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                ])
                ->default('gpt-4o-mini')
                ->required(),
        ]);
    }

    public function validateSettings(array $data): array
    {
        $validated = $this->validate($data, [
            'prompt' => 'required|string',
            'output_schema' => 'required|string',
            'schema_template' => 'required|string',
            'model' => 'required|string',
        ]);

        // Validate that output_schema is valid JSON
        $schema = json_decode($validated['output_schema'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['output_schema' => ['The output schema must be valid JSON']]
            );
        }

        // Ensure schema has required structure
        if (! isset($schema['type']) || $schema['type'] !== 'object') {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['output_schema' => ['The output schema must be an object type']]
            );
        }

        return $validated;
    }

    public function process(PipelineContext $context): PipelineResult
    {
        try {
            $prompt = $this->buildPrompt($context);
            $schema = json_decode($this->setting('output_schema'), true);
            $model = $this->setting('model', 'gpt-4o-mini');

            $response = $this->callOpenAI($prompt, $schema, $model);

            // Extract value, justification, and confidence from response
            $value = $response['value'] ?? null;
            $justification = $response['justification'] ?? null;
            $confidence = $response['confidence'] ?? null;

            // Track token usage in meta
            $meta = [];
            if (isset($response['_usage'])) {
                $meta['tokens_in'] = $response['_usage']['prompt_tokens'] ?? 0;
                $meta['tokens_out'] = $response['_usage']['completion_tokens'] ?? 0;
                $meta['model'] = $model;
            }

            return PipelineResult::ok($value, $confidence, $justification, $meta);
        } catch (\Exception $e) {
            return PipelineResult::error('AI processing failed: '.$e->getMessage());
        }
    }

    /**
     * Build the complete prompt with inputs
     */
    protected function buildPrompt(PipelineContext $context): string
    {
        $prompt = $this->setting('prompt');

        // Append inputs as list
        $inputLines = [];
        foreach ($context->allInputs() as $key => $value) {
            $inputLines[] = "{$key}: {$value}";
        }

        if (! empty($inputLines)) {
            $prompt .= "\n\nInputs:\n".implode("\n", $inputLines);
        }

        return $prompt;
    }

    /**
     * Call OpenAI API
     */
    protected function callOpenAI(string $prompt, array $schema, string $model): array
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'pipeline_output',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'OpenAI API request failed: '.$response->body()
            );
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (! $content) {
            throw new \RuntimeException('No content in OpenAI response');
        }

        $result = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse OpenAI response as JSON');
        }

        // Attach usage stats
        $result['_usage'] = $data['usage'] ?? [];

        return $result;
    }
}
