<?php

namespace App\Filament\Resources\PipelineResource\Pages;

use App\Filament\Resources\PipelineResource;
use App\Pipelines\PipelineModuleRegistry;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Builder;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class EditPipeline extends EditRecord
{
    protected static string $resource = PipelineResource::class;

    public function form(Schema $schema): Schema
    {
        $registry = app(PipelineModuleRegistry::class);

        return $schema->components([
            Tabs::make('Pipeline')
                ->columnSpanFull()
                ->tabs([
                    Tabs\Tab::make('Statistics')
                        ->icon('heroicon-o-chart-bar')
                        ->schema([
                            Section::make('Last Run Stats')
                                ->schema([
                                    Forms\Components\Placeholder::make('last_run')
                                        ->label('Last Run')
                                        ->content(fn ($record) => $record->last_run_at?->diffForHumans() ?? 'Never'),

                                    Forms\Components\Placeholder::make('status')
                                        ->label('Status')
                                        ->content(fn ($record) => $record->last_run_status ?? '—'),

                                    Forms\Components\Placeholder::make('processed')
                                        ->label('Entities Processed')
                                        ->content(fn ($record) => $record->last_run_processed ?? '—'),

                                    Forms\Components\Placeholder::make('failed')
                                        ->label('Failed')
                                        ->content(fn ($record) => $record->last_run_failed ?? '—'),

                                    Forms\Components\Placeholder::make('tokens')
                                        ->label('Tokens (In/Out)')
                                        ->content(function ($record) {
                                            if (!$record->last_run_tokens_in && !$record->last_run_tokens_out) {
                                                return '—';
                                            }
                                            return number_format($record->last_run_tokens_in ?? 0) . ' / ' . number_format($record->last_run_tokens_out ?? 0);
                                        }),

                                    Forms\Components\Placeholder::make('error_message')
                                        ->label('Error Message')
                                        ->content(function ($record) {
                                            if ($record->last_run_status !== 'failed') {
                                                return null;
                                            }
                                            $lastRun = $record->runs()->latest('started_at')->first();
                                            return $lastRun?->error_message ?? 'No error message available';
                                        })
                                        ->visible(fn ($record) => $record->last_run_status === 'failed')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),

                            Section::make('Token Usage (Last 30 Days)')
                                ->schema([
                                    Forms\Components\Placeholder::make('token_stats')
                                        ->label('')
                                        ->content(function ($record) {
                                            $stats = $record->getTokenUsage(30);
                                            return implode(' | ', [
                                                'Total: ' . number_format($stats['total_tokens']),
                                                'Avg per entity: ' . $stats['avg_tokens_per_entity'],
                                            ]);
                                        }),
                                ])
                                ->collapsible(),
                        ]),

                    Tabs\Tab::make('Configuration')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Section::make('Pipeline Information')
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Pipeline Name')
                                        ->maxLength(255)
                                        ->nullable()
                                        ->helperText('Optional friendly name for this pipeline.'),

                                    Forms\Components\Placeholder::make('entity_type')
                                        ->label('Entity Type')
                                        ->content(fn ($record) => $record->entityType->name ?? '—'),

                                    Forms\Components\Placeholder::make('attribute')
                                        ->label('Target Attribute')
                                        ->content(fn ($record) => $record->attribute->name ?? '—'),

                                    Forms\Components\Placeholder::make('version')
                                        ->label('Pipeline Version')
                                        ->content(fn ($record) => $record->pipeline_version),

                                    Forms\Components\Placeholder::make('updated')
                                        ->label('Last Updated')
                                        ->content(fn ($record) => $record->pipeline_updated_at?->diffForHumans()),
                                ])
                                ->columns(2),

                            Section::make('Entity Filtering')
                                ->description('Optional: Only run this pipeline on entities matching specific conditions.')
                                ->schema([
                                    Forms\Components\Select::make('entity_filter.attribute_id')
                                        ->label('Filter by Attribute')
                                        ->options(function ($record) {
                                            if (!$record || !$record->entity_type_id) {
                                                return [];
                                            }
                                            return \App\Models\Attribute::where('entity_type_id', $record->entity_type_id)
                                                ->orderBy('display_name')
                                                ->get()
                                                ->mapWithKeys(fn($attr) => [
                                                    $attr->id => $attr->display_name ?? $attr->name
                                                ])
                                                ->toArray();
                                        })
                                        ->searchable()
                                        ->placeholder('Select an attribute to filter by')
                                        ->helperText('Only run pipeline on entities matching this condition')
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            if (!$state) {
                                                $set('entity_filter.operator', null);
                                                $set('entity_filter.value', null);
                                            }
                                        }),

                                    Forms\Components\Select::make('entity_filter.operator')
                                        ->label('Operator')
                                        ->options(function (callable $get) {
                                            $attributeId = $get('entity_filter.attribute_id');
                                            if (!$attributeId) {
                                                return [];
                                            }

                                            $attribute = \App\Models\Attribute::find($attributeId);
                                            if (!$attribute) {
                                                return [];
                                            }

                                            // For select/multiselect, limit operators
                                            if (in_array($attribute->data_type, ['select', 'multiselect'])) {
                                                return [
                                                    '=' => 'Equals',
                                                    '!=' => 'Not Equals',
                                                    'in' => 'In List',
                                                    'not_in' => 'Not In List',
                                                    'null' => 'Is Null',
                                                    'not_null' => 'Is Not Null',
                                                ];
                                            }

                                            // Full operator set for other types
                                            return [
                                                '=' => 'Equals',
                                                '!=' => 'Not Equals',
                                                '>' => 'Greater Than',
                                                '>=' => 'Greater Than or Equal',
                                                '<' => 'Less Than',
                                                '<=' => 'Less Than or Equal',
                                                'in' => 'In List',
                                                'not_in' => 'Not In List',
                                                'null' => 'Is Null',
                                                'not_null' => 'Is Not Null',
                                                'contains' => 'Contains',
                                            ];
                                        })
                                        ->default('=')
                                        ->required(fn (callable $get) => (bool) $get('entity_filter.attribute_id'))
                                        ->visible(fn (callable $get) => (bool) $get('entity_filter.attribute_id'))
                                        ->live(),

                                    // Select dropdown for select/multiselect attributes
                                    Forms\Components\Select::make('entity_filter.value')
                                        ->label('Value')
                                        ->options(function (callable $get) {
                                            $attributeId = $get('entity_filter.attribute_id');
                                            if (!$attributeId) {
                                                return [];
                                            }

                                            $attribute = \App\Models\Attribute::find($attributeId);
                                            if (!$attribute || !in_array($attribute->data_type, ['select', 'multiselect'])) {
                                                return [];
                                            }

                                            // Return allowed_values (key => label format)
                                            return $attribute->allowed_values ?? [];
                                        })
                                        ->multiple(function (callable $get) {
                                            $operator = $get('entity_filter.operator');
                                            return in_array($operator, ['in', 'not_in']);
                                        })
                                        ->searchable()
                                        ->required(function (callable $get) {
                                            $attributeId = $get('entity_filter.attribute_id');
                                            $operator = $get('entity_filter.operator');
                                            if (!$attributeId || in_array($operator, ['null', 'not_null'])) {
                                                return false;
                                            }
                                            $attribute = \App\Models\Attribute::find($attributeId);
                                            return $attribute && in_array($attribute->data_type, ['select', 'multiselect']);
                                        })
                                        ->visible(function (callable $get) {
                                            $attributeId = $get('entity_filter.attribute_id');
                                            $operator = $get('entity_filter.operator');
                                            if (!$attributeId || in_array($operator, ['null', 'not_null'])) {
                                                return false;
                                            }
                                            $attribute = \App\Models\Attribute::find($attributeId);
                                            return $attribute && in_array($attribute->data_type, ['select', 'multiselect']);
                                        }),

                                    // Text input for other attribute types
                                    Forms\Components\TextInput::make('entity_filter.value')
                                        ->label('Value')
                                        ->helperText('For "In List" or "Not In List", separate values with commas')
                                        ->required(function (callable $get) {
                                            $attributeId = $get('entity_filter.attribute_id');
                                            $operator = $get('entity_filter.operator');
                                            if (!$attributeId || in_array($operator, ['null', 'not_null'])) {
                                                return false;
                                            }
                                            $attribute = \App\Models\Attribute::find($attributeId);
                                            return $attribute && !in_array($attribute->data_type, ['select', 'multiselect']);
                                        })
                                        ->visible(function (callable $get) {
                                            $attributeId = $get('entity_filter.attribute_id');
                                            $operator = $get('entity_filter.operator');
                                            if (!$attributeId || in_array($operator, ['null', 'not_null'])) {
                                                return false;
                                            }
                                            $attribute = \App\Models\Attribute::find($attributeId);
                                            return $attribute && !in_array($attribute->data_type, ['select', 'multiselect']);
                                        }),
                                ])
                                ->columns(3)
                                ->collapsible()
                                ->collapsed(),

                            Section::make('Processing Modules')
                                ->description('Define the sequence of operations that generate the attribute value. First module must load source data, subsequent modules transform it.')
                                ->schema([
                                    Builder::make('modules_config')
                                        ->label('')
                                        ->blocks($this->getModuleBlocks($registry))
                                        ->collapsible()
                                        ->blockNumbers(false)
                                        ->addActionLabel('Add Module')
                                        ->reorderable()
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            // This would trigger validation, but we'll handle it on save
                                        })
                                        ->default([])
                                        ->columnSpanFull(),
                                ])
                                ->collapsible(),
                        ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('run_pipeline')
                ->label('Run Pipeline')
                ->icon('heroicon-o-play')
                ->form([
                    Forms\Components\Radio::make('run_type')
                        ->label('How many entities to process?')
                        ->options([
                            'all' => 'Run All Entities',
                            'sample' => 'Run on 100 Entities (for testing)',
                        ])
                        ->default('all')
                        ->required(),

                    Forms\Components\Checkbox::make('force')
                        ->label('Force Reprocess')
                        ->helperText('Reprocess all entities even if inputs haven\'t changed. Useful for testing or when modules have been updated.')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    $maxEntities = $data['run_type'] === 'sample' ? 100 : null;
                    $force = $data['force'] ?? false;

                    \App\Jobs\Pipeline\RunPipelineBatch::dispatch(
                        pipeline: $this->record,
                        triggeredBy: 'manual',
                        maxEntities: $maxEntities,
                        force: $force
                    );

                    $message = $data['run_type'] === 'sample'
                        ? 'The pipeline will run on the first 100 entities' . ($force ? ' (forced reprocess).' : '.')
                        : 'All entities will be processed' . ($force ? ' (forced reprocess).' : '.');

                    \Filament\Notifications\Notification::make()
                        ->title('Pipeline Queued')
                        ->body($message)
                        ->success()
                        ->send();
                })
                ->modalHeading('Run Pipeline')
                ->modalSubmitActionLabel('Run Pipeline'),

            Actions\Action::make('run_evals')
                ->label('Run Evals')
                ->icon('heroicon-o-beaker')
                ->action(function () {
                    \App\Jobs\Pipeline\RunPipelineEvals::dispatch($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Evals Queued')
                        ->body('Evaluation tests have been queued.')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Build module blocks for the Builder component
     */
    protected function getModuleBlocks(PipelineModuleRegistry $registry): array
    {
        $blocks = [];

        // Get all registered modules
        foreach ($registry->all() as $definition) {
            $moduleClass = $registry->getClass($definition->id);

            // Get module-specific form components
            $moduleComponents = $this->getModuleFormComponents($definition->id, $moduleClass);

            $blocks[] = Builder\Block::make($definition->id)
                ->label($definition->label)
                ->icon($definition->isSource() ? 'heroicon-o-folder-open' : 'heroicon-o-cpu-chip')
                ->schema([
                    Forms\Components\Hidden::make('module_class')
                        ->default($moduleClass),

                    Forms\Components\Placeholder::make('description')
                        ->label('Description')
                        ->content($definition->description),

                    ...$moduleComponents,
                ]);
        }

        return $blocks;
    }

    /**
     * Get form components for a specific module
     * TODO: Refactor modules to return components directly instead of using non-existent Form class
     */
    protected function getModuleFormComponents(string $moduleId, string $moduleClass): array
    {
        // Hardcode the form fields for each known module type
        // This is a temporary workaround until we refactor the module form system
        return match ($moduleId) {
            'attributes_source' => [
                Forms\Components\Select::make('attribute_ids')
                    ->label('Attributes')
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->options(function () {
                        return \App\Models\Attribute::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->helperText('Select the attributes to use as inputs for this pipeline'),
            ],
            'ai_prompt' => [
                Forms\Components\Textarea::make('prompt')
                    ->label('Prompt')
                    ->required()
                    ->rows(10)
                    ->helperText('The prompt to send to OpenAI. Input attributes will be appended automatically.'),
                Forms\Components\Select::make('schema_template')
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
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Update output_schema when template changes (unless custom)
                        if ($state !== 'custom') {
                            $schemas = [
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

                            if (isset($schemas[$state])) {
                                $set('output_schema', json_encode($schemas[$state], JSON_PRETTY_PRINT));
                            }
                        }
                    })
                    ->required(),
                Forms\Components\Textarea::make('output_schema')
                    ->label('Output Schema (JSON)')
                    ->required()
                    ->rows(10)
                    ->default('{"type":"object","properties":{"value":{"type":"string"},"justification":{"type":"string"},"confidence":{"type":"number","minimum":0,"maximum":1}},"required":["value","justification","confidence"],"additionalProperties":false}')
                    ->helperText('OpenAI-compatible JSON schema for structured output'),
                Forms\Components\Select::make('model')
                    ->label('Model')
                    ->options([
                        'gpt-4o' => 'GPT-4o (latest, recommended)',
                        'gpt-4o-mini' => 'GPT-4o Mini (faster, cheaper)',
                        'gpt-4-turbo' => 'GPT-4 Turbo',
                    ])
                    ->default('gpt-4o-mini')
                    ->required(),
            ],
            'calculation' => [
                Forms\Components\Textarea::make('code')
                    ->label('JavaScript Code')
                    ->required()
                    ->rows(25)
                    ->extraAttributes(['class' => 'font-mono text-sm'])
                    ->helperText('Write JavaScript to transform input attributes. Available: $json (all inputs as object). Return an object with value, justification, and confidence.')
                    ->placeholder(<<<'JS'
// Example 1: Calculate total from quantity and price
const total = ($json.quantity || 0) * ($json.price || 0);
return {
    value: total,
    justification: `Calculated from ${$json.quantity} × ${$json.price}`,
    confidence: 1.0
};

// Example 2: Conditional logic
const status = $json.stock > 100 ? 'In Stock' : 'Low Stock';
return {
    value: status,
    justification: `Stock level is ${$json.stock}`,
    confidence: $json.stock > 0 ? 1.0 : 0.5
};

// Example 3: String manipulation
const title = ($json.brand + ' ' + $json.name).trim().toUpperCase();
return {
    value: title,
    justification: 'Combined brand and name, uppercase',
    confidence: 1.0
};
JS
),
            ],
            default => [
                Forms\Components\Placeholder::make('no_config')
                    ->label('Configuration')
                    ->content('This module has no configuration options.'),
            ],
        };
    }

    /**
     * Fill form with existing module and eval data
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $registry = app(PipelineModuleRegistry::class);

        // Load existing modules into the builder format
        $modules = $this->record->modules()->orderBy('order')->get();

        $modulesConfig = [];
        foreach ($modules as $module) {
            $definition = $registry->getDefinition($module->module_class);

            $modulesConfig[] = [
                'type' => $definition->id,
                'data' => array_merge(
                    ['module_class' => $module->module_class],
                    $module->settings ?? []
                ),
            ];
        }

        $data['modules_config'] = $modulesConfig;

        // Handle entity_filter value display
        if (isset($data['entity_filter']['value']) && is_array($data['entity_filter']['value'])) {
            $filter = $data['entity_filter'];
            $attribute = \App\Models\Attribute::find($filter['attribute_id'] ?? null);

            // For select/multiselect with in/not_in operators, keep as array (multi-select will handle it)
            // For other cases (text input), convert to comma-separated string
            if (!$attribute || !in_array($attribute->data_type, ['select', 'multiselect'])) {
                $data['entity_filter']['value'] = implode(', ', $data['entity_filter']['value']);
            }
            // Otherwise keep as array for the select component
        }

        return $data;
    }

    /**
     * Save modules from form to database
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $modulesConfig = $data['modules_config'] ?? [];

        // Don't save this to pipeline table
        unset($data['modules_config']);

        // Handle entity_filter value conversion for in/not_in operators
        if (isset($data['entity_filter'])) {
            $filter = $data['entity_filter'];

            // If no attribute selected, clear the entire filter
            if (empty($filter['attribute_id'])) {
                $data['entity_filter'] = null;
            } else {
                $attribute = \App\Models\Attribute::find($filter['attribute_id']);

                // For in/not_in operators
                if (in_array($filter['operator'] ?? '', ['in', 'not_in']) && isset($filter['value'])) {
                    // If attribute is select/multiselect, value is already an array from the select component
                    if ($attribute && in_array($attribute->data_type, ['select', 'multiselect'])) {
                        // Value is already an array, ensure it's an array
                        $data['entity_filter']['value'] = is_array($filter['value']) ? $filter['value'] : [$filter['value']];
                    } else {
                        // For text inputs, split by comma
                        if (is_string($filter['value'])) {
                            $values = array_map('trim', explode(',', $filter['value']));
                            $data['entity_filter']['value'] = $values;
                        }
                    }
                }

                // Remove value for null/not_null operators
                if (in_array($filter['operator'] ?? '', ['null', 'not_null'])) {
                    unset($data['entity_filter']['value']);
                }
            }
        }

        return $data;
    }

    /**
     * After save, sync modules
     */
    protected function afterSave(): void
    {
        $modulesConfig = $this->data['modules_config'] ?? [];
        $registry = app(PipelineModuleRegistry::class);

        // Validate module configuration
        if (!empty($modulesConfig)) {
            $errors = $this->validateModuleConfiguration($modulesConfig, $registry);

            if (!empty($errors)) {
                \Filament\Notifications\Notification::make()
                    ->title('Module Configuration Error')
                    ->body(implode(' ', $errors))
                    ->danger()
                    ->send();
                return;
            }
        }

        // Delete existing modules
        $this->record->modules()->delete();

        // Create new modules
        $order = 1;
        foreach ($modulesConfig as $moduleBlock) {
            // Skip if not an array or doesn't have required structure
            if (!is_array($moduleBlock) || !isset($moduleBlock['type'])) {
                continue;
            }

            // Get the module class from the type (which is the module ID)
            $moduleClass = $registry->getClass($moduleBlock['type']);
            if (!$moduleClass) {
                continue;
            }

            // Extract settings from data field
            $settings = $moduleBlock['data'] ?? [];

            // Remove internal fields
            unset($settings['module_class']);
            unset($settings['description']);

            // Remove any placeholder fields
            $settings = array_filter($settings, function($key) {
                return !str_ends_with($key, '_display') && !str_ends_with($key, '_note');
            }, ARRAY_FILTER_USE_KEY);

            $this->record->modules()->create([
                'order' => $order++,
                'module_class' => $moduleClass,
                'settings' => $settings,
            ]);
        }

        // Bump pipeline version if modules changed
        if (!empty($modulesConfig)) {
            $this->record->increment('pipeline_version');
            $this->record->update(['pipeline_updated_at' => now()]);
        }
    }

    /**
     * Validate module configuration follows rules
     */
    protected function validateModuleConfiguration(array $modulesConfig, PipelineModuleRegistry $registry): array
    {
        $errors = [];

        if (empty($modulesConfig)) {
            return $errors;
        }

        // First module must be a source
        $firstModuleType = $modulesConfig[0]['type'] ?? null;
        if ($firstModuleType) {
            $firstModuleClass = $registry->getClass($firstModuleType);
            if ($firstModuleClass) {
                $firstDef = $registry->getDefinition($firstModuleClass);
                if ($firstDef && !$firstDef->isSource()) {
                    $errors[] = 'First module must be a source module (loads input data).';
                }
            }
        }

        // Subsequent modules must be processors
        foreach (array_slice($modulesConfig, 1) as $index => $moduleBlock) {
            $moduleType = $moduleBlock['type'] ?? null;
            if ($moduleType) {
                $moduleClass = $registry->getClass($moduleType);
                if ($moduleClass) {
                    $def = $registry->getDefinition($moduleClass);
                    if ($def && !$def->isProcessor()) {
                        $errors[] = "Module at position " . ($index + 2) . " must be a processor module.";
                    }
                }
            }
        }

        // Must have at least one processor
        if (count($modulesConfig) < 2) {
            $errors[] = 'Pipeline must have at least one processor module (in addition to the source).';
        }

        return $errors;
    }
}

